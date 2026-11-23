import 'dart:convert';

import 'package:http/http.dart' as http;

/// API base URL, set at build time:
/// web: `--dart-define=API_BASE_URL=/api/v1` (same origin through Caddy)
/// Android: `--dart-define=API_BASE_URL=https://PUBLIC_HOST/api/v1`
const _configuredBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://localhost:8080/api/v1',
);

Uri defaultBaseUri() {
  // A relative URL ("/api/v1") means "same server as the web page".
  return _configuredBaseUrl.startsWith('/')
      ? Uri.base.resolve(_configuredBaseUrl)
      : Uri.parse(_configuredBaseUrl);
}

/// Error returned by the API: `{ "message": "...", "errors": { "field": ["..."] } }`.
class ApiException implements Exception {
  ApiException(this.statusCode, this.message, [this.errors = const {}]);

  final int statusCode;
  final String message;
  final Map<String, List<String>> errors;

  bool get isUnauthenticated => statusCode == 401;
  bool get isNetworkError => statusCode == 0;

  /// First validation message for [field], if any.
  String? fieldError(String field) => errors[field]?.first;

  @override
  String toString() => 'ApiException($statusCode, $message)';
}

/// Thin JSON client for the Anecnotes REST API.
class ApiClient {
  ApiClient({http.Client? httpClient, Uri? baseUri})
      : _http = httpClient ?? http.Client(),
        baseUri = baseUri ?? defaultBaseUri();

  final http.Client _http;
  final Uri baseUri;
  String? token;

  /// Called when the server rejects the token (expired, revoked, account disabled).
  void Function()? onUnauthenticated;

  Future<Map<String, dynamic>> get(String path, {Map<String, String>? query}) =>
      _send('GET', path, query: query);

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) =>
      _send('POST', path, body: body);

  Future<Map<String, dynamic>> patch(String path, Map<String, dynamic> body) =>
      _send('PATCH', path, body: body);

  Future<void> delete(String path) => _send('DELETE', path);

  Uri _uri(String path, Map<String, String>? query) {
    final base = baseUri.toString().replaceAll(RegExp(r'/+$'), '');
    final uri = Uri.parse('$base$path');
    return query == null || query.isEmpty ? uri : uri.replace(queryParameters: query);
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final request = http.Request(method, _uri(path, query))
      ..headers.addAll({
        'Accept': 'application/json',
        if (body != null) 'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      });
    if (body != null) request.body = jsonEncode(body);

    final http.Response response;
    try {
      response = await http.Response.fromStream(
        await _http.send(request).timeout(const Duration(seconds: 20)),
      );
    } catch (_) {
      throw ApiException(0, 'Cannot reach the server. Check your connection and try again.');
    }

    final decoded = response.body.isEmpty ? null : _tryDecode(response.body);
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
    }

    final map = decoded is Map<String, dynamic> ? decoded : const <String, dynamic>{};
    final errors = <String, List<String>>{};
    if (map['errors'] is Map) {
      (map['errors'] as Map).forEach((k, v) {
        errors['$k'] = (v as List).map((e) => '$e').toList();
      });
    }
    final error = ApiException(
      response.statusCode,
      (map['message'] as String?) ?? 'Something went wrong (HTTP ${response.statusCode}).',
      errors,
    );
    if (error.isUnauthenticated && token != null) onUnauthenticated?.call();
    throw error;
  }

  Object? _tryDecode(String body) {
    try {
      return jsonDecode(body);
    } catch (_) {
      return null;
    }
  }
}
