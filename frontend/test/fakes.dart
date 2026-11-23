import 'dart:convert';

import 'package:anecnotes/api/anecdote_repository.dart';
import 'package:anecnotes/api/api_client.dart';
import 'package:anecnotes/auth/auth_controller.dart';
import 'package:anecnotes/main.dart';
import 'package:flutter/widgets.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class MemoryTokenStore implements TokenStore {
  String? token;
  @override
  Future<void> clear() async => token = null;
  @override
  Future<String?> read() async => token;
  @override
  Future<void> write(String t) async => token = t;
}

/// A tiny in-memory stand-in for the Anecnotes API.
class FakeServer {
  FakeServer({this.role = 'teacher', this.userId = 1});

  final String role;
  final int userId;
  final requests = <http.Request>[];
  final anecdotes = <Map<String, dynamic>>[];

  static Map<String, dynamic> anecdote({
    String id = 'a1',
    int authorId = 1,
    String status = 'open',
    Map<String, dynamic>? analysis,
    String description = 'Pushed a classmate during recess and called him names.',
  }) => {
        'id': id,
        'student_id': 7,
        'student': {'id': 7, 'name': 'Dela Cruz, Juan', 'lrn': '999900000001'},
        'section': '8-Rizal',
        'author_id': authorId,
        'author': 'Maria Santos',
        'occurred_at': '2026-11-23T02:15:00Z',
        'location': 'Canteen',
        'description': description,
        'action_taken': null,
        'category_override': null,
        'severity_override': null,
        'status': status,
        'resolved_by': null,
        'resolved_at': null,
        'resolution_note': null,
        'analysis': analysis ??
            {'status': 'done', 'category': 'aggression_bullying', 'severity': 'medium', 'confidence': 0.82, 'label': 'AI suggestion'},
      };

  late final client = MockClient((request) async {
    requests.add(request);
    final path = request.url.path.replaceFirst('/api/v1', '');
    final body = request.body.isEmpty ? <String, dynamic>{} : jsonDecode(request.body) as Map<String, dynamic>;

    http.Response json(Object data, [int status = 200]) =>
        http.Response(jsonEncode(data), status, headers: {'content-type': 'application/json'});

    if (path == '/auth/login') {
      if (body['password'] != 'right') return json({'message': 'These credentials do not match our records.'}, 401);
      return json({'token': 'tok', 'user': _user()});
    }
    if (request.headers['Authorization'] != 'Bearer tok') return json({'message': 'Unauthenticated.'}, 401);
    if (path == '/auth/me') return json({..._user(), 'advised_sections': []});
    if (path == '/auth/logout') return http.Response('', 204);
    if (path == '/students') {
      return json({
        'data': [
          {'id': 7, 'lrn': '999900000001', 'last_name': 'Dela Cruz', 'first_name': 'Juan', 'section': '8-Rizal'},
        ],
        'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
      });
    }
    if (path == '/anecdotes' && request.method == 'GET') {
      return json({'data': anecdotes, 'meta': {'current_page': 1, 'last_page': 1, 'total': anecdotes.length}});
    }
    if (path == '/anecdotes' && request.method == 'POST') {
      final a = anecdote(id: body['id'] as String, authorId: userId, description: body['description'] as String, analysis: {'status': 'pending', 'label': 'Analysis pending'});
      anecdotes.insert(0, a);
      return json({'data': a}, 201);
    }
    final match = RegExp(r'^/anecdotes/([^/]+)(/resolve)?$').firstMatch(path);
    if (match != null) {
      final a = anecdotes.firstWhere((x) => x['id'] == match.group(1));
      if (match.group(2) != null) {
        a['status'] = 'resolved';
        a['resolution_note'] = body['resolution_note'];
      } else if (request.method == 'PATCH') {
        a.addAll(body);
      } else if (request.method == 'DELETE') {
        anecdotes.remove(a);
        return http.Response('', 204);
      }
      return json({'data': a});
    }
    return json({'message': 'Record not found.'}, 404);
  });

  Map<String, dynamic> _user() => {'id': userId, 'name': 'Maria Santos', 'email': 'adviser@demo.test', 'role': role, 'is_active': true};

  /// Builds the app wired to this fake server.
  Widget app({MemoryTokenStore? store, bool signedIn = false}) {
    final api = ApiClient(httpClient: client, baseUri: Uri.parse('http://test/api/v1'));
    final tokens = store ?? MemoryTokenStore();
    if (signedIn) tokens.token = 'tok';
    final auth = AuthController(api, tokens)..restore();
    return AnecnotesApp(auth: auth, anecdotes: AnecdoteRepository(api));
  }
}
