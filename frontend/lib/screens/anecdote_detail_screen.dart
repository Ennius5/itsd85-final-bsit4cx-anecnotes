import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../app_scope.dart';
import '../models/models.dart';
import '../widgets/analysis_card.dart';
import 'anecdote_form_screen.dart';

/// Wireframe 05 · Anecdote with AI suggestion (US-11, US-12, US-13).
class AnecdoteDetailScreen extends StatefulWidget {
  const AnecdoteDetailScreen({super.key, required this.id});

  final String id;

  @override
  State<AnecdoteDetailScreen> createState() => _AnecdoteDetailScreenState();
}

class _AnecdoteDetailScreenState extends State<AnecdoteDetailScreen> {
  Anecdote? _a;
  String? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final a = await AppScope.of(context).anecdotes.get(widget.id);
      setState(() {
        _a = a;
        _error = null;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    }
  }

  Future<void> _run(Future<Anecdote?> Function() action, String done) async {
    setState(() => _busy = true);
    try {
      final updated = await action();
      if (!mounted) return;
      if (updated != null) setState(() => _a = updated);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(done)));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _edit() async {
    final updated = await Navigator.of(context).push<Anecdote>(
      MaterialPageRoute(builder: (_) => AnecdoteFormScreen(existing: _a)),
    );
    if (updated != null) setState(() => _a = updated);
  }

  Future<void> _changeCategory() async {
    final a = _a!;
    String? category = a.finalCategory;
    String? severity = a.finalSeverity;
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setLocal) => AlertDialog(
          title: const Text('Correct the category'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            DropdownButtonFormField<String>(
              key: const Key('category-field'),
              initialValue: category,
              decoration: const InputDecoration(labelText: 'Category'),
              items: [for (final e in categoryLabels.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
              onChanged: (v) => setLocal(() => category = v),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: severity,
              decoration: const InputDecoration(labelText: 'Severity'),
              items: [for (final e in severityLabels.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
              onChanged: (v) => setLocal(() => severity = v),
            ),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Save')),
          ],
        ),
      ),
    );
    if (result != true || !mounted) return;
    final repo = AppScope.of(context).anecdotes;
    await _run(
      () => repo.update(a.id, {'category_override': category, 'severity_override': severity}),
      'Category saved. The AI suggestion is kept for the record.',
    );
  }

  Future<void> _resolve() async {
    final note = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Resolve anecdote'),
        content: TextField(
          key: const Key('resolution-note'),
          controller: note,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Resolution note *', hintText: 'What was done?'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Resolve')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final repo = AppScope.of(context).anecdotes;
    await _run(() => repo.resolve(_a!.id, note.text.trim()), 'Anecdote resolved.');
  }

  Future<void> _delete() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this anecdote?'),
        content: const Text('It will be removed from lists. An admin can still see it in the audit log.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Theme.of(context).colorScheme.error),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final repo = AppScope.of(context).anecdotes;
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    try {
      await repo.delete(_a!.id);
      messenger.showSnackBar(const SnackBar(content: Text('Anecdote deleted.')));
      navigator.pop();
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = AppScope.of(context).auth.user!;
    final a = _a;

    if (a == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Anecdote')),
        body: Center(child: _error != null ? Text(_error!) : const CircularProgressIndicator()),
      );
    }

    // Mirrors the server policy; the server still checks every action.
    final isAuthor = a.authorId == user.id;
    final canEditText = isAuthor && a.isOpen;
    final canOverride = (isAuthor && a.isOpen) || user.isGuidance || user.isAdmin;
    final canResolve = user.isGuidance && a.isOpen;
    final canDelete = user.isAdmin || (isAuthor && a.isOpen);
    final fmt = DateFormat('MMM d, yyyy · h:mm a');
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Anecdote'),
        actions: [
          if (canEditText) IconButton(key: const Key('edit'), tooltip: 'Edit', icon: const Icon(Icons.edit), onPressed: _busy ? null : _edit),
          if (canDelete) IconButton(key: const Key('delete'), tooltip: 'Delete', icon: const Icon(Icons.delete), onPressed: _busy ? null : _delete),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(a.studentName, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
            Text([if (a.section != null) a.section!, if (a.author != null) 'By ${a.author}'].join(' · ')),
            Text([fmt.format(a.occurredAt), if (a.location != null) a.location!].join(' · '), style: theme.textTheme.bodySmall),
            const SizedBox(height: 16),
            Text(a.description, style: theme.textTheme.bodyLarge),
            if (a.actionTaken != null) ...[
              const SizedBox(height: 8),
              Text('Action taken: ${a.actionTaken}'),
            ],
            const SizedBox(height: 16),
            AnalysisCard(anecdote: a),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [
              if (canOverride)
                OutlinedButton.icon(
                  key: const Key('change-category'),
                  onPressed: _busy ? null : _changeCategory,
                  icon: const Icon(Icons.label),
                  label: const Text('Change category'),
                ),
              if (canResolve)
                FilledButton.icon(
                  key: const Key('resolve'),
                  onPressed: _busy ? null : _resolve,
                  icon: const Icon(Icons.check),
                  label: const Text('Resolve'),
                ),
            ]),
            const SizedBox(height: 16),
            if (a.isOpen)
              const Text('Status: Open')
            else ...[
              Text('Status: Resolved${a.resolvedAt != null ? ' on ${fmt.format(a.resolvedAt!)}' : ''}',
                  style: const TextStyle(fontWeight: FontWeight.bold)),
              if (a.resolutionNote != null) Text(a.resolutionNote!),
            ],
          ],
        ),
      ),
    );
  }
}
