import '../widgets/school_navigation.dart';
import 'package:flutter/material.dart';

import '../services/api_client.dart';
import '../services/session_service.dart';
import '../services/theme_service.dart';
import 'child_detail_screen.dart';
import 'enrollment_screen.dart';
import 'no_class_days_screen.dart';
import 'notifications_screen.dart';
import 'parent_biometric_screen.dart';
import 'parent_excuse_screen.dart';
import 'student_directory_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.api,
    required this.session,
    required this.onLogout,
    required this.theme,
    required this.onThemeChanged,
    required this.fontScale,
    required this.highContrast,
    required this.onFontScaleChanged,
    required this.onHighContrastChanged,
    this.contentManagementEnabled = true,
    this.onContentManagementChanged,
  });

  final ApiClient api;
  final SessionService session;
  final VoidCallback onLogout;
  final ThemePreset theme;
  final ValueChanged<ThemePreset> onThemeChanged;
  final double fontScale;
  final bool highContrast;
  final ValueChanged<double> onFontScaleChanged;
  final ValueChanged<bool> onHighContrastChanged;
  final bool contentManagementEnabled;
  final ValueChanged<bool>? onContentManagementChanged;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  String _userName = 'Parent';
  int _childrenCount = 0;
  int _unread = 0;
  int _pendingLetters = 0;
  int _pendingEnrollments = 0;
  List<Map<String, dynamic>> _students = [];
  List<Map<String, dynamic>> _notifications = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final name = await widget.session.userName();
      final dash = await widget.api.get('/parent/dashboard');
      final students = await widget.api.get('/students');
      final notifications = await widget.api.get('/notifications');
      final excuseRequests = await widget.api.get('/parent/excuse-requests');
      final enrollmentRequests = await widget.api.get(
        '/parent/enrollment-requests',
      );
      final dashData = dash['data'] as Map<String, dynamic>;
      final studentList = (students['data'] as List)
          .cast<Map<String, dynamic>>();
      final recentNotifications = (notifications['data'] as List? ?? [])
          .cast<Map<String, dynamic>>();
      final excuseList = (excuseRequests['data']['requests'] as List? ?? []);
      final enrollmentList = (enrollmentRequests['data'] as List? ?? []);

      setState(() {
        _userName = name ?? 'Parent';
        _childrenCount =
            dashData['children_count'] as int? ?? studentList.length;
        _unread = dashData['unread_notifications'] as int? ?? 0;
        _pendingLetters = excuseList
            .where((item) => item['status'] == 'pending')
            .length;
        _pendingEnrollments = enrollmentList
            .where((item) => item['status'] == 'pending')
            .length;
        _students = studentList;
        _notifications = recentNotifications.take(4).toList();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _logout() async {
    await widget.session.logout();
    if (!mounted) return;
    widget.onLogout();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: SchoolNavigation(
        api: widget.api,
        teacher: false,
        theme: widget.theme,
        onThemeChanged: widget.onThemeChanged,
        fontScale: widget.fontScale,
        highContrast: widget.highContrast,
        onFontScaleChanged: widget.onFontScaleChanged,
        onHighContrastChanged: widget.onHighContrastChanged,
        contentManagementEnabled: widget.contentManagementEnabled,
        onContentManagementChanged: widget.onContentManagementChanged,
        onReturn: () {
          if (mounted) _load();
        },
      ),
      appBar: AppBar(
        title: const Text('Parent Dashboard'),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => NotificationsScreen(
                    api: widget.api,
                    onOpenExcuseLetters: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ParentExcuseScreen(api: widget.api),
                        ),
                      );
                    },
                  ),
                ),
              );
              _load();
            },
            icon: Badge(
              isLabelVisible: _unread > 0,
              label: Text('$_unread'),
              child: const Icon(Icons.notifications_outlined),
            ),
          ),
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout)),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  SchoolWelcome(name: _userName, role: 'Parent'),
                  const SizedBox(height: 12),
                  Text(
                    'Keep up with your children’s attendance, school updates, and requests in one place.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 16),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    childAspectRatio: 1.65,
                    children: [
                      _DashboardSummaryCard(
                        label: 'My children',
                        value: '$_childrenCount',
                        icon: Icons.people_alt_outlined,
                        color: Colors.blue,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                StudentDirectoryScreen(api: widget.api),
                          ),
                        ),
                      ),
                      _DashboardSummaryCard(
                        label: 'Unread notifications',
                        value: '$_unread',
                        icon: Icons.notifications_outlined,
                        color: Colors.indigo,
                        onTap: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => NotificationsScreen(
                                api: widget.api,
                                onOpenExcuseLetters: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          ParentExcuseScreen(api: widget.api),
                                    ),
                                  );
                                },
                              ),
                            ),
                          );
                          _load();
                        },
                      ),
                      _DashboardSummaryCard(
                        label: 'Letters needing reply',
                        value: '$_pendingLetters',
                        icon: Icons.mail_outline,
                        color: Colors.orange,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => ParentExcuseScreen(api: widget.api),
                          ),
                        ),
                      ),
                      _DashboardSummaryCard(
                        label: 'Pending enrollments',
                        value: '$_pendingEnrollments',
                        icon: Icons.person_add_alt_1,
                        color: Colors.teal,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => EnrollmentScreen(api: widget.api),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (_unread > 0)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.orange.shade50,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.orange.shade200),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.notifications_active_outlined,
                            color: Colors.orange,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              '$_unread unread notification${_unread == 1 ? '' : 's'} waiting for you.',
                              style: const TextStyle(color: Colors.orange),
                            ),
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: 20),
                  Text(
                    'Quick links',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    childAspectRatio: 1.8,
                    children: [
                      _QuickActionCard(
                        title: 'Attendance records',
                        subtitle: 'Check attendance and departure times.',
                        icon: Icons.fact_check_outlined,
                        color: Colors.blue,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                StudentDirectoryScreen(api: widget.api),
                          ),
                        ),
                      ),
                      _QuickActionCard(
                        title: 'No-class days',
                        subtitle: 'Plan around holidays and suspensions.',
                        icon: Icons.event_busy_outlined,
                        color: Colors.teal,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => NoClassDaysScreen(api: widget.api),
                          ),
                        ),
                      ),
                      _QuickActionCard(
                        title: 'Explanation letters',
                        subtitle: 'Reply to attendance concerns.',
                        icon: Icons.mail_outline,
                        color: Colors.orange,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => ParentExcuseScreen(api: widget.api),
                          ),
                        ),
                      ),
                      _QuickActionCard(
                        title: 'Notifications',
                        subtitle: 'Read school alerts and updates.',
                        icon: Icons.notifications_outlined,
                        color: Colors.indigo,
                        onTap: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => NotificationsScreen(
                                api: widget.api,
                                onOpenExcuseLetters: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          ParentExcuseScreen(api: widget.api),
                                    ),
                                  );
                                },
                              ),
                            ),
                          );
                          _load();
                        },
                      ),
                      _QuickActionCard(
                        title: 'Enroll a child',
                        subtitle:
                            'Submit a child’s details or track the request.',
                        icon: Icons.person_add_alt_1,
                        color: Colors.teal,
                        onTap: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => EnrollmentScreen(api: widget.api),
                            ),
                          );
                          _load();
                        },
                      ),
                      _QuickActionCard(
                        title: 'Biometric face photos',
                        subtitle:
                            'Upload your child’s face photos for attendance.',
                        icon: Icons.face_retouching_natural,
                        color: Colors.purple,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                ParentBiometricScreen(api: widget.api),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Recent notifications',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      TextButton(
                        onPressed: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => NotificationsScreen(
                                api: widget.api,
                                onOpenExcuseLetters: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          ParentExcuseScreen(api: widget.api),
                                    ),
                                  );
                                },
                              ),
                            ),
                          );
                          _load();
                        },
                        child: const Text('View all'),
                      ),
                    ],
                  ),
                  if (_notifications.isEmpty)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Center(
                          child: Text(
                            'No notifications yet. School attendance updates will appear here.',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      ),
                    )
                  else
                    ..._notifications.take(4).map((n) {
                      final read = n['read_at'] != null;
                      final title =
                          n['title']?.toString() ?? 'Attendance update';
                      final body = n['body']?.toString() ?? '';
                      return Card(
                        child: ListTile(
                          leading: CircleAvatar(
                            radius: 16,
                            backgroundColor: read
                                ? Colors.grey.shade200
                                : Colors.blue.shade50,
                            child: Icon(
                              Icons.notifications_outlined,
                              size: 18,
                              color: read ? Colors.grey : Colors.blue,
                            ),
                          ),
                          title: Text(title),
                          subtitle: Text(body),
                          trailing: !read ? const Text('Unread') : null,
                        ),
                      );
                    }),
                  const SizedBox(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'My Children',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      TextButton.icon(
                        onPressed: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => EnrollmentScreen(api: widget.api),
                            ),
                          );
                          _load();
                        },
                        icon: const Icon(Icons.person_add_alt_1),
                        label: const Text('Enroll child'),
                      ),
                    ],
                  ),
                  if (_students.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text(
                        'No linked children yet. Submit an LRN for teacher verification.',
                      ),
                    ),
                  ..._students.map((student) {
                    final name =
                        '${student['first_name']} ${student['last_name']}';
                    return Card(
                      child: ListTile(
                        title: Text(name),
                        subtitle: Text(
                          student['section']?.toString() ?? 'No section',
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => ChildDetailScreen(
                              api: widget.api,
                              studentId: student['id'] as int,
                              studentName: name,
                            ),
                          ),
                        ),
                      ),
                    );
                  }),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                  ],
                ],
              ),
            ),
    );
  }
}

class _DashboardSummaryCard extends StatelessWidget {
  const _DashboardSummaryCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(label, style: Theme.of(context).textTheme.bodySmall),
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(icon, size: 20, color: color),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                value,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _QuickActionCard extends StatelessWidget {
  const _QuickActionCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: color),
              ),
              const SizedBox(height: 10),
              Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
              const SizedBox(height: 6),
              Expanded(
                child: Text(
                  subtitle,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
