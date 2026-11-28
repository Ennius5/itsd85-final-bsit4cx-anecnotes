import 'dart:convert';

import 'package:anecnotes/api/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  ApiClient clientReturning(int status, Object body) => ApiClient(
        baseUri: Uri.parse('http://test/api/v1'),
        httpClient: MockClient((_) async => http.Response(jsonEncode(body), status)),
      );

  test('parses validation errors', () async {
    final api = clientReturning(422, {
      'message': 'The description field must be at least 10 characters.',
      'errors': {'description': ['The description field must be at least 10 characters.']},
    });

    await expectLater(
      api.post('/anecdotes', {}),
      throwsA(isA<ApiException>()
          .having((e) => e.statusCode, 'status', 422)
          .having((e) => e.fieldError('description'), 'field', contains('10 characters'))),
    );
  });

  test('a rejected token signs the user out', () async {
    var signedOut = false;
    final api = clientReturning(401, {'message': 'Unauthenticated.'})
      ..token = 'expired'
      ..onUnauthenticated = () => signedOut = true;

    await expectLater(api.get('/auth/me'), throwsA(isA<ApiException>()));
    expect(signedOut, isTrue);
  });

  test('reports network failures with a friendly message', () async {
    final api = ApiClient(
      baseUri: Uri.parse('http://test/api/v1'),
      httpClient: MockClient((_) async => throw http.ClientException('offline')),
    );

    await expectLater(
      api.get('/anecdotes'),
      throwsA(isA<ApiException>().having((e) => e.isNetworkError, 'network', isTrue)),
    );
  });

  test('sends the bearer token and JSON headers', () async {
    late http.Request seen;
    final api = ApiClient(
      baseUri: Uri.parse('http://test/api/v1'),
      httpClient: MockClient((r) async {
        seen = r;
        return http.Response('{}', 200);
      }),
    )..token = 'abc';

    await api.post('/anecdotes', {'x': 1});
    expect(seen.headers['Authorization'], 'Bearer abc');
    expect(seen.headers['Accept'], 'application/json');
    expect(seen.url.toString(), 'http://test/api/v1/anecdotes');
  });
}
