import 'dart:async';

import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../app_scope.dart';
import '../models/models.dart';

/// Full-screen search for a student by name or LRN.
Future<Student?> showStudentPicker(BuildContext context) =>
    Navigator.of(context).push<Student>(MaterialPageRoute(fullscreenDialog: true, builder: (_) => const _StudentPicker()));

class _StudentPicker extends StatefulWidget {
  const _StudentPicker();

  @override
  State<_StudentPicker> createState() => _StudentPickerState();
}

class _StudentPickerState extends State<_StudentPicker> {
  final _query = TextEditingController();
  Timer? _debounce;
  List<Student> _results = [];
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _debounce?.cancel();
    _query.dispose();
    super.dispose();
  }

  void _onChanged(String text) {
    _debounce?.cancel();
    if (text.trim().length < 2) {
      setState(() => _results = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 350), () => _search(text.trim()));
  }

  Future<void> _search(String q) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final r = await AppScope.of(context).anecdotes.searchStudents(q);
      if (mounted && _query.text.trim() == q) setState(() => _results = r);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          key: const Key('student-search'),
          controller: _query,
          autofocus: true,
          onChanged: _onChanged,
          style: const TextStyle(color: Colors.white),
          cursorColor: Colors.white,
          decoration: const InputDecoration(
            hintText: 'Name or LRN',
            hintStyle: TextStyle(color: Colors.white70),
            border: InputBorder.none,
          ),
        ),
      ),
      body: Column(children: [
        if (_loading) const LinearProgressIndicator(),
        if (_error != null) Padding(padding: const EdgeInsets.all(16), child: Text(_error!)),
        if (!_loading && _error == null && _results.isEmpty)
          Padding(
            padding: const EdgeInsets.all(24),
            child: Text(_query.text.trim().length < 2 ? 'Type at least 2 letters.' : 'No students found.'),
          ),
        Expanded(
          child: ListView.builder(
            itemCount: _results.length,
            itemBuilder: (context, i) {
              final s = _results[i];
              return ListTile(
                key: Key('student-${s.id}'),
                title: Text(s.fullName),
                subtitle: Text([if (s.section != null) s.section!, 'LRN ${s.lrn}'].join(' · ')),
                onTap: () => Navigator.of(context).pop(s),
              );
            },
          ),
        ),
      ]),
    );
  }
}
