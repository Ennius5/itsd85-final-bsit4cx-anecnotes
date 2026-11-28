import 'package:flutter/widgets.dart';

import 'api/anecdote_repository.dart';
import 'auth/auth_controller.dart';

/// Makes the auth controller and repositories available to every screen.
class AppScope extends InheritedWidget {
  const AppScope({super.key, required this.auth, required this.anecdotes, required super.child});

  final AuthController auth;
  final AnecdoteRepository anecdotes;

  static AppScope of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AppScope>();
    assert(scope != null, 'No AppScope above this widget');
    return scope!;
  }

  @override
  bool updateShouldNotify(AppScope oldWidget) => auth != oldWidget.auth || anecdotes != oldWidget.anecdotes;
}
