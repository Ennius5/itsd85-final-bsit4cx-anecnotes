import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'fakes.dart';

void main() {
  group('sign in', () {
    testWidgets('shows the API error for a wrong password', (tester) async {
      final server = FakeServer();
      await tester.pumpWidget(server.app());
      await tester.pumpAndSettle();

      await tester.enterText(find.byKey(const Key('email')), 'adviser@demo.test');
      await tester.enterText(find.byKey(const Key('password')), 'wrong');
      await tester.tap(find.byKey(const Key('sign-in')));
      await tester.pumpAndSettle();

      expect(find.text('These credentials do not match our records.'), findsOneWidget);
    });

    testWidgets('signs in, saves the token and opens the anecdote list', (tester) async {
      final server = FakeServer()..anecdotes.add(FakeServer.anecdote());
      final store = MemoryTokenStore();
      await tester.pumpWidget(server.app(store: store));
      await tester.pumpAndSettle();

      await tester.enterText(find.byKey(const Key('email')), 'Adviser@Demo.test');
      await tester.enterText(find.byKey(const Key('password')), 'right');
      await tester.tap(find.byKey(const Key('sign-in')));
      await tester.pumpAndSettle();

      expect(store.token, 'tok');
      expect(find.text('Anecdotes'), findsOneWidget);
      expect(find.text('Dela Cruz, Juan'), findsOneWidget);
      expect(find.textContaining('Aggression / bullying · Medium'), findsOneWidget);

      final login = server.requests.firstWhere((r) => r.url.path.endsWith('/auth/login'));
      expect(jsonDecode(login.body)['email'], 'adviser@demo.test'); // trimmed and lower-cased
    });

    testWidgets('unreadable token storage falls back to the sign-in screen', (tester) async {
      await tester.pumpWidget(FakeServer().app(store: BrokenTokenStore()));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('sign-in')), findsOneWidget);
    });

    testWidgets('signing out clears the token', (tester) async {
      final store = MemoryTokenStore();
      await tester.pumpWidget(FakeServer().app(store: store, signedIn: true));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('account-menu')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sign out'));
      await tester.pumpAndSettle();

      expect(store.token, isNull);
      expect(find.byKey(const Key('sign-in')), findsOneWidget);
    });
  });

  group('anecdotes', () {
    testWidgets('creates an anecdote with a client UUID and device time', (tester) async {
      final server = FakeServer();
      await tester.pumpWidget(server.app(signedIn: true));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('new-anecdote')));
      await tester.pumpAndSettle();

      // Saving without a student is blocked on the phone.
      final appBarSave = find.widgetWithText(TextButton, 'Save');
      await tester.tap(appBarSave);
      await tester.pumpAndSettle();
      expect(find.text('Choose a student.'), findsOneWidget);

      await tester.tap(find.byKey(const Key('student-field')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('student-search')), 'dela');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('student-7')));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Canteen'));
      await tester.enterText(find.byKey(const Key('description')), 'Hit a classmate with a ball on purpose during PE.');
      await tester.tap(appBarSave);
      await tester.pumpAndSettle();

      final post = server.requests.lastWhere((r) => r.method == 'POST' && r.url.path.endsWith('/anecdotes'));
      final body = jsonDecode(post.body) as Map<String, dynamic>;
      expect(body['student_id'], 7);
      expect(body['location'], 'Canteen');
      expect(body['id'], matches(RegExp(r'^[0-9a-f-]{36}$')));
      expect(body['device_time'], isNotNull);

      expect(find.text('Anecdote saved.'), findsOneWidget);
      expect(find.text('Analysis pending'), findsOneWidget);
    });

    testWidgets('shows the AI suggestion labeled as machine-generated', (tester) async {
      final server = FakeServer()..anecdotes.add(FakeServer.anecdote());
      await tester.pumpWidget(server.app(signedIn: true));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Dela Cruz, Juan'));
      await tester.pumpAndSettle();

      expect(find.text('AI suggestion (machine-generated)'), findsOneWidget);
      expect(find.textContaining('Confidence 0.82'), findsOneWidget);
      // The author may edit and delete; only guidance may resolve.
      expect(find.byKey(const Key('edit')), findsOneWidget);
      expect(find.byKey(const Key('delete')), findsOneWidget);
      expect(find.byKey(const Key('resolve')), findsNothing);
    });

    testWidgets('hides edit and delete from other teachers', (tester) async {
      final server = FakeServer(userId: 2)..anecdotes.add(FakeServer.anecdote(authorId: 1));
      await tester.pumpWidget(server.app(signedIn: true));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Dela Cruz, Juan'));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('edit')), findsNothing);
      expect(find.byKey(const Key('delete')), findsNothing);
      expect(find.byKey(const Key('change-category')), findsNothing);
    });

    testWidgets('guidance resolves an anecdote with a note', (tester) async {
      final server = FakeServer(role: 'guidance', userId: 3)..anecdotes.add(FakeServer.anecdote());
      await tester.pumpWidget(server.app(signedIn: true));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Dela Cruz, Juan'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('resolve')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('resolution-note')), 'Met the student and parent.');
      await tester.tap(find.widgetWithText(FilledButton, 'Resolve').last);
      await tester.pumpAndSettle();

      expect(find.text('Anecdote resolved.'), findsOneWidget);
      expect(find.textContaining('Status: Resolved'), findsOneWidget);
      expect(server.anecdotes.first['resolution_note'], 'Met the student and parent.');
    });

    testWidgets('the author deletes an anecdote after confirming', (tester) async {
      final server = FakeServer()..anecdotes.add(FakeServer.anecdote());
      await tester.pumpWidget(server.app(signedIn: true));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Dela Cruz, Juan'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('delete')));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Delete'));
      await tester.pumpAndSettle();

      expect(server.anecdotes, isEmpty);
      expect(find.text('Dela Cruz, Juan'), findsNothing);
    });
  });
}
