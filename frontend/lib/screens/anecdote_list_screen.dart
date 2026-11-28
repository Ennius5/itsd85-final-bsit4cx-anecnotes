import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../app_scope.dart';
import '../models/models.dart';
import '../widgets/analysis_card.dart';
import 'anecdote_detail_screen.dart';
import 'anecdote_form_screen.dart';

/// Home: the anecdotes this user may see, newest first (US-10, US-12).
class AnecdoteListScreen extends StatefulWidget {
  const AnecdoteListScreen({super.key});

  @override
  State<AnecdoteListScreen> createState() => _AnecdoteListScreenState();
}

class _AnecdoteListScreenState extends State<AnecdoteListScreen> {
  final _items = <Anecdote>[];
  final _search = TextEditingController();
  Timer? _debounce;
  String? _status = 'open';
  int _page = 0;
  bool _hasMore = true;
  bool _loading = false;
  String? _error;
  int _total = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload());
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    _items.clear();
    _page = 0;
    _hasMore = true;
    await _loadMore();
  }

  Future<void> _loadMore() async {
    if (_loading || !_hasMore) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await AppScope.of(context).anecdotes.list(page: _page + 1, query: _search.text.trim(), status: _status);
      setState(() {
        _items.addAll(page.items);
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _total = page.total;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), _reload);
  }

  Future<void> _openNew() async {
    final created = await Navigator.of(context).push<Anecdote>(
      MaterialPageRoute(builder: (_) => const AnecdoteFormScreen()),
    );
    if (created != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Anecdote saved.')));
      await _reload();
    }
  }

  Future<void> _openDetail(Anecdote a) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => AnecdoteDetailScreen(id: a.id)));
    if (mounted) await _reload();
  }

  @override
  Widget build(BuildContext context) {
    final auth = AppScope.of(context).auth;
    final user = auth.user!;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Anecdotes'),
        actions: [
          PopupMenuButton<String>(
            key: const Key('account-menu'),
            icon: const Icon(Icons.account_circle),
            onSelected: (v) {
              if (v == 'signout') auth.signOut();
            },
            itemBuilder: (_) => [
              PopupMenuItem(
                enabled: false,
                child: Text('${user.name}\n${user.roleLabel}'
                    '${user.advisedSections.isNotEmpty ? ' · Adviser of ${user.advisedSections.join(', ')}' : ''}'),
              ),
              const PopupMenuItem(value: 'signout', child: Text('Sign out')),
            ],
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('new-anecdote'),
        onPressed: _openNew,
        icon: const Icon(Icons.add),
        label: const Text('Anecdote'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: TextField(
              key: const Key('search'),
              controller: _search,
              onChanged: _onSearchChanged,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search),
                hintText: 'Search student or text',
                isDense: true,
              ),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(children: [
              for (final (value, label) in [('open', 'Open'), ('resolved', 'Resolved'), (null, 'All')])
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(label),
                    selected: _status == value,
                    onSelected: (_) {
                      setState(() => _status = value);
                      _reload();
                    },
                  ),
                ),
              if (!_loading) Text('$_total found', style: Theme.of(context).textTheme.bodySmall),
            ]),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _reload,
              child: _buildList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildList() {
    if (_error != null && _items.isEmpty) {
      return ListView(children: [
        Padding(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: _reload, child: const Text('Try again')),
          ]),
        ),
      ]);
    }
    if (_items.isEmpty && !_loading) {
      return ListView(children: const [
        Padding(padding: EdgeInsets.all(24), child: Text('No anecdotes yet. Tap + Anecdote to write one.', textAlign: TextAlign.center)),
      ]);
    }

    final fmt = DateFormat('MMM d, h:mm a');
    return ListView.separated(
      padding: const EdgeInsets.only(bottom: 88),
      itemCount: _items.length + 1,
      separatorBuilder: (_, _) => const Divider(height: 1),
      itemBuilder: (context, i) {
        if (i == _items.length) {
          if (_loading) return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
          if (_hasMore) return TextButton(onPressed: _loadMore, child: const Text('Load more'));
          return const SizedBox.shrink();
        }
        final a = _items[i];
        return ListTile(
          key: Key('anecdote-${a.id}'),
          title: Text(a.studentName, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(a.description, maxLines: 2, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 2),
              Text(
                [fmt.format(a.occurredAt), if (a.section != null) a.section!, if (a.location != null) a.location!].join(' · '),
                style: const TextStyle(fontSize: 12),
              ),
              CategoryChip(anecdote: a),
            ],
          ),
          trailing: a.isOpen ? null : const Icon(Icons.check_circle, color: Colors.green),
          isThreeLine: true,
          onTap: () => _openDetail(a),
        );
      },
    );
  }
}
