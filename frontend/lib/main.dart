import 'package:flutter/material.dart';

/// API base URL, injected at build time:
/// web: --dart-define=API_BASE_URL=/api/v1 (same origin through Caddy)
/// Android: `--dart-define=API_BASE_URL=https://PUBLIC_HOST/api/v1`
const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://localhost:8080/api/v1',
);

void main() {
  runApp(const AnecnotesApp());
}

class AnecnotesApp extends StatelessWidget {
  const AnecnotesApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Anecnotes',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1F3864)),
      ),
      home: const Scaffold(
        body: Center(child: Text('Anecnotes')),
      ),
    );
  }
}
