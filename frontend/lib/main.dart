import 'package:flutter/material.dart';

import 'api/anecdote_repository.dart';
import 'api/api_client.dart';
import 'app_scope.dart';
import 'auth/auth_controller.dart';
import 'screens/anecdote_list_screen.dart';
import 'screens/login_screen.dart';

void main() {
  final api = ApiClient();
  final auth = AuthController(api, SecureTokenStore())..restore();
  runApp(AnecnotesApp(auth: auth, anecdotes: AnecdoteRepository(api)));
}

const brandColor = Color(0xFF1F3864);

class AnecnotesApp extends StatelessWidget {
  const AnecnotesApp({super.key, required this.auth, required this.anecdotes});

  final AuthController auth;
  final AnecdoteRepository anecdotes;

  @override
  Widget build(BuildContext context) {
    return AppScope(
      auth: auth,
      anecdotes: anecdotes,
      child: MaterialApp(
        title: 'Anecnotes',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(seedColor: brandColor),
          appBarTheme: const AppBarTheme(backgroundColor: brandColor, foregroundColor: Colors.white),
          inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
        ),
        home: ListenableBuilder(
          listenable: auth,
          builder: (context, _) => switch (auth.status) {
            AuthStatus.unknown => const Scaffold(body: Center(child: CircularProgressIndicator())),
            AuthStatus.signedOut => const LoginScreen(),
            AuthStatus.signedIn => const AnecdoteListScreen(),
          },
        ),
      ),
    );
  }
}
