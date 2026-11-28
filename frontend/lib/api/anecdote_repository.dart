import 'package:uuid/uuid.dart';

import '../models/models.dart';
import 'api_client.dart';

/// Anecdote and student calls. Online-only in M3; the offline outbox wraps this in M4.
class AnecdoteRepository {
  AnecdoteRepository(this._api);

  final ApiClient _api;
  static const _uuid = Uuid();

  Future<Paged<Anecdote>> list({int page = 1, String? query, String? status}) async {
    final j = await _api.get('/anecdotes', query: {
      'page': '$page',
      'per_page': '20',
      if (query != null && query.isNotEmpty) 'q': query,
      'status': ?status,
    });
    final meta = j['meta'] as Map<String, dynamic>;
    return Paged(
      [for (final a in j['data'] as List) Anecdote.fromJson(a as Map<String, dynamic>)],
      meta['current_page'] as int,
      meta['last_page'] as int,
      meta['total'] as int,
    );
  }

  Future<Anecdote> get(String id) async =>
      Anecdote.fromJson((await _api.get('/anecdotes/$id'))['data'] as Map<String, dynamic>);

  /// Creates an anecdote with a client-generated id, so a retry never creates a duplicate.
  Future<Anecdote> create({
    required int studentId,
    required DateTime occurredAt,
    required String description,
    String? location,
    String? actionTaken,
    String? id,
  }) async {
    final j = await _api.post('/anecdotes', {
      'id': id ?? _uuid.v4(),
      'student_id': studentId,
      'occurred_at': occurredAt.toUtc().toIso8601String(),
      'location': _blankToNull(location),
      'description': description.trim(),
      'action_taken': _blankToNull(actionTaken),
      'device_time': DateTime.now().toUtc().toIso8601String(),
    });
    return Anecdote.fromJson(j['data'] as Map<String, dynamic>);
  }

  Future<Anecdote> update(String id, Map<String, dynamic> changes) async =>
      Anecdote.fromJson((await _api.patch('/anecdotes/$id', changes))['data'] as Map<String, dynamic>);

  Future<void> delete(String id) => _api.delete('/anecdotes/$id');

  Future<Anecdote> resolve(String id, String note) async => Anecdote.fromJson(
        (await _api.post('/anecdotes/$id/resolve', {'resolution_note': note}))['data'] as Map<String, dynamic>,
      );

  Future<List<Student>> searchStudents(String query) async {
    final j = await _api.get('/students', query: {'q': query, 'per_page': '20'});
    return [for (final s in j['data'] as List) Student.fromJson(s as Map<String, dynamic>)];
  }

  static String? _blankToNull(String? v) => v == null || v.trim().isEmpty ? null : v.trim();
}
