import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../app_scope.dart';
import '../models/models.dart';
import 'student_picker.dart';

/// Wireframe 04 · Write or edit an anecdote (US-10, US-11).
/// Pops with the saved [Anecdote].
class AnecdoteFormScreen extends StatefulWidget {
  const AnecdoteFormScreen({super.key, this.existing});

  final Anecdote? existing;

  @override
  State<AnecdoteFormScreen> createState() => _AnecdoteFormScreenState();
}

const _locations = ['Classroom', 'Canteen', 'Hallway', 'Field', 'Other'];

class _AnecdoteFormScreenState extends State<AnecdoteFormScreen> {
  final _form = GlobalKey<FormState>();
  late final _description = TextEditingController(text: widget.existing?.description);
  late final _action = TextEditingController(text: widget.existing?.actionTaken);
  late final _location = TextEditingController(text: widget.existing?.location);
  Student? _student;
  late DateTime _occurredAt = widget.existing?.occurredAt ?? DateTime.now();
  bool _busy = false;
  bool _missingStudent = false;
  ApiException? _error;

  bool get _editing => widget.existing != null;

  @override
  void dispose() {
    _description.dispose();
    _action.dispose();
    _location.dispose();
    super.dispose();
  }

  Future<void> _pickStudent() async {
    final s = await showStudentPicker(context);
    if (s != null) {
      setState(() {
        _student = s;
        _missingStudent = false;
      });
    }
  }

  Future<void> _pickDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _occurredAt,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now(),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(_occurredAt));
    if (time == null) return;
    setState(() => _occurredAt = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  Future<void> _save() async {
    final formValid = _form.currentState!.validate();
    setState(() => _missingStudent = !_editing && _student == null);
    if (_missingStudent || !formValid) return;

    setState(() {
      _busy = true;
      _error = null;
    });
    final repo = AppScope.of(context).anecdotes;
    try {
      final saved = _editing
          ? await repo.update(widget.existing!.id, {
              'occurred_at': _occurredAt.toUtc().toIso8601String(),
              'location': _location.text.trim().isEmpty ? null : _location.text.trim(),
              'description': _description.text.trim(),
              'action_taken': _action.text.trim().isEmpty ? null : _action.text.trim(),
            })
          : await repo.create(
              studentId: _student!.id,
              occurredAt: _occurredAt,
              description: _description.text,
              location: _location.text,
              actionTaken: _action.text,
            );
      if (mounted) Navigator.of(context).pop(saved);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final studentLabel = _editing ? widget.existing!.studentName : (_student == null ? null : '${_student!.fullName}${_student!.section != null ? '  (${_student!.section})' : ''}');

    return Scaffold(
      appBar: AppBar(
        title: Text(_editing ? 'Edit anecdote' : 'New anecdote'),
        actions: [
          TextButton(
            onPressed: _busy ? null : _save,
            style: TextButton.styleFrom(foregroundColor: Colors.white),
            child: const Text('Save'),
          ),
        ],
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            InkWell(
              key: const Key('student-field'),
              onTap: _editing ? null : _pickStudent,
              child: InputDecorator(
                decoration: InputDecoration(
                  labelText: 'Student *',
                  suffixIcon: _editing ? null : const Icon(Icons.search),
                  errorText: _missingStudent ? 'Choose a student.' : _error?.fieldError('student_id'),
                ),
                child: Text(studentLabel ?? 'Tap to search by name or LRN',
                    style: studentLabel == null ? TextStyle(color: theme.hintColor) : null),
              ),
            ),
            const SizedBox(height: 16),
            InkWell(
              onTap: _pickDateTime,
              child: InputDecorator(
                decoration: const InputDecoration(labelText: 'When *', suffixIcon: Icon(Icons.schedule)),
                child: Text(DateFormat('MMM d, yyyy · h:mm a').format(_occurredAt)),
              ),
            ),
            const SizedBox(height: 12),
            Wrap(spacing: 8, children: [
              for (final place in _locations)
                ChoiceChip(
                  label: Text(place),
                  selected: _location.text == place,
                  onSelected: (_) => setState(() => _location.text = place),
                ),
            ]),
            const SizedBox(height: 12),
            TextFormField(
              controller: _location,
              decoration: InputDecoration(labelText: 'Location (optional)', errorText: _error?.fieldError('location')),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 16),
            TextFormField(
              key: const Key('description'),
              controller: _description,
              minLines: 4,
              maxLines: 10,
              maxLength: 5000,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                labelText: 'What happened? *',
                alignLabelWithHint: true,
                helperText: 'Describe what you saw. English or Filipino.',
                errorText: _error?.fieldError('description'),
              ),
              validator: (v) => (v == null || v.trim().length < 10) ? 'Write at least 10 characters.' : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _action,
              minLines: 2,
              maxLines: 5,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(labelText: 'Action taken (optional)', alignLabelWithHint: true),
            ),
            if (_error != null && _error!.errors.isEmpty) ...[
              const SizedBox(height: 12),
              Text(_error!.message, key: const Key('form-error'), style: TextStyle(color: theme.colorScheme.error)),
            ],
            const SizedBox(height: 16),
            FilledButton(
              key: const Key('save'),
              onPressed: _busy ? null : _save,
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
              child: _busy
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(_editing ? 'Save changes' : 'Save anecdote'),
            ),
            if (!_editing) ...[
              const SizedBox(height: 8),
              Text('An AI suggests a category after saving. You can correct it.', style: theme.textTheme.bodySmall),
            ],
          ],
        ),
      ),
    );
  }
}
