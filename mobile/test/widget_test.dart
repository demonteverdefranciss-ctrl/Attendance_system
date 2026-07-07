import 'package:flutter_test/flutter_test.dart';

import 'package:attendance_parent/main.dart';

void main() {
  testWidgets('App boots to login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const AttendanceParentApp());
    await tester.pumpAndSettle();

    expect(find.text('Bigaa Elementary School'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
