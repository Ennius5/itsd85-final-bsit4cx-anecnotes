import 'package:anecnotes/main.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('app starts and shows its name', (tester) async {
    await tester.pumpWidget(const AnecnotesApp());
    expect(find.text('Anecnotes'), findsOneWidget);
  });
}
