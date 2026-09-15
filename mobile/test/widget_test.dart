import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:attendance_parent/main.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));
  testWidgets('App boots to login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const AttendanceParentApp());
    await tester.pumpAndSettle();

    expect(find.text('Bigaa Elementary School'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
