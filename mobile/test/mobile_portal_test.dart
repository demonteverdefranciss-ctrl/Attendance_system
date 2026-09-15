import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:attendance_parent/services/api_client.dart';
import 'package:attendance_parent/screens/no_class_days_screen.dart';
import 'package:attendance_parent/screens/student_directory_screen.dart';
import 'package:attendance_parent/widgets/school_navigation.dart';

void main() {
  testWidgets('Calendar shows upcoming school dates and today badge', (
    tester,
  ) async {
    final api = ApiClient(
      client: MockClient((request) async {
        expect(request.url.path, endsWith('/no-class-days'));
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'today': '2026-09-16',
              'upcoming': [
                {
                  'date': '2026-09-16',
                  'weekday': 'Wednesday',
                  'label': 'September 16, 2026',
                  'name': 'School holiday',
                },
              ],
              'recent': [],
            },
          }),
          200,
        );
      }),
    );
    await tester.pumpWidget(MaterialApp(home: NoClassDaysScreen(api: api)));
    await tester.pumpAndSettle();
    expect(find.text('School holiday'), findsOneWidget);
    expect(find.text('Today'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Student search filters results by LRN', (tester) async {
    final api = ApiClient(
      client: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'success': true,
            'data': [
              {
                'id': 1,
                'first_name': 'Ana',
                'last_name': 'Cruz',
                'lrn': '123',
                'section': 'A',
              },
              {
                'id': 2,
                'first_name': 'Ben',
                'last_name': 'Reyes',
                'lrn': '456',
                'section': 'B',
              },
            ],
          }),
          200,
        ),
      ),
    );
    await tester.pumpWidget(
      MaterialApp(home: StudentDirectoryScreen(api: api)),
    );
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), '456');
    await tester.pump();
    expect(find.text('Ana Cruz'), findsNothing);
    expect(find.text('Ben Reyes'), findsOneWidget);
  });
  testWidgets('Calendar errors offer retry and recover', (tester) async {
    var attempts = 0;
    final api = ApiClient(
      client: MockClient((_) async {
        attempts++;
        return attempts == 1
            ? http.Response('{}', 500)
            : http.Response(
                jsonEncode({
                  'success': true,
                  'data': {'upcoming': [], 'recent': []},
                }),
                200,
              );
      }),
    );
    await tester.pumpWidget(MaterialApp(home: NoClassDaysScreen(api: api)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(attempts, 2);
    expect(find.text('Retry'), findsNothing);
  });
  testWidgets('School welcome fits a narrow phone with enlarged text', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(1.5)),
          child: const Scaffold(
            body: SchoolWelcome(
              name: 'Parent with a long name',
              role: 'Parent',
            ),
          ),
        ),
      ),
    );
    expect(tester.takeException(), isNull);
  });
}
