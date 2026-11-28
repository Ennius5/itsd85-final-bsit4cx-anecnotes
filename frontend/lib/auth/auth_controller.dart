import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api/api_client.dart';
import '../models/models.dart';

/// Where the login token is kept. On Android it is encrypted with the Keystore.
abstract class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> clear();
}

class SecureTokenStore implements TokenStore {
  static const _key = 'anecnotes_token';
  final _storage = const FlutterSecureStorage();

  @override
  Future<String?> read() => _storage.read(key: _key);
  @override
  Future<void> write(String token) => _storage.write(key: _key, value: token);
  @override
  Future<void> clear() => _storage.delete(key: _key);
}

enum AuthStatus { unknown, signedOut, signedIn }

/// Holds the signed-in user and token; screens listen to it.
class AuthController extends ChangeNotifier {
  AuthController(this.api, this._store) {
    api.onUnauthenticated = () => _signOutLocally();
  }

  final ApiClient api;
  final TokenStore _store;

  AuthStatus status = AuthStatus.unknown;
  User? user;

  /// Restores a saved session at app start.
  Future<void> restore() async {
    final token = await _store.read();
    if (token == null) {
      status = AuthStatus.signedOut;
      notifyListeners();
      return;
    }
    api.token = token;
    try {
      user = User.fromJson(await api.get('/auth/me'));
      status = AuthStatus.signedIn;
    } on ApiException catch (e) {
      if (e.isNetworkError) {
        // Offline sign-in comes with the local database in M4; for now ask to sign in again.
        api.token = null;
      } else {
        await _store.clear();
        api.token = null;
      }
      status = AuthStatus.signedOut;
    }
    notifyListeners();
  }

  /// Throws [ApiException] with a message suitable for display.
  Future<void> signIn(String email, String password) async {
    final j = await api.post('/auth/login', {
      'email': email.trim().toLowerCase(),
      'password': password,
      'device_name': kIsWeb ? 'Web browser' : 'Android app',
    });
    final token = j['token'] as String;
    api.token = token;
    await _store.write(token);
    user = User.fromJson(await api.get('/auth/me'));
    status = AuthStatus.signedIn;
    notifyListeners();
  }

  Future<void> signOut() async {
    try {
      await api.post('/auth/logout');
    } on ApiException {
      // Sign out locally even if the server cannot be reached.
    }
    await _signOutLocally();
  }

  Future<void> _signOutLocally() async {
    api.token = null;
    user = null;
    await _store.clear();
    status = AuthStatus.signedOut;
    notifyListeners();
  }
}
