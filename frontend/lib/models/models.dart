/// Data classes matching the API's JSON (see docs/03-api/openapi.yaml).
library;

enum Role { admin, teacher, guidance }

class User {
  User({required this.id, required this.name, required this.email, required this.role, this.advisedSections = const []});

  factory User.fromJson(Map<String, dynamic> j) => User(
        id: j['id'] as int,
        name: j['name'] as String,
        email: j['email'] as String,
        role: Role.values.byName(j['role'] as String),
        advisedSections: [
          for (final s in (j['advised_sections'] as List? ?? const [])) (s as Map)['label'] as String,
        ],
      );

  final int id;
  final String name;
  final String email;
  final Role role;
  final List<String> advisedSections;

  bool get isGuidance => role == Role.guidance;
  bool get isAdmin => role == Role.admin;

  String get roleLabel => switch (role) {
        Role.admin => 'Admin',
        Role.teacher => 'Teacher',
        Role.guidance => 'Guidance',
      };
}

class Student {
  Student({required this.id, required this.lrn, required this.lastName, required this.firstName, this.section});

  factory Student.fromJson(Map<String, dynamic> j) => Student(
        id: j['id'] as int,
        lrn: j['lrn'] as String,
        lastName: j['last_name'] as String,
        firstName: j['first_name'] as String,
        section: j['section'] as String?,
      );

  final int id;
  final String lrn;
  final String lastName;
  final String firstName;
  final String? section;

  String get fullName => '$lastName, $firstName';
}

/// Anecdote categories, with labels for display.
const categoryLabels = <String, String>{
  'attendance_tardiness': 'Attendance / tardiness',
  'disruptive_behavior': 'Disruptive behavior',
  'defiance_disrespect': 'Defiance / disrespect',
  'aggression_bullying': 'Aggression / bullying',
  'academic_concern': 'Academic concern',
  'wellbeing_concern': 'Wellbeing concern',
  'positive_behavior': 'Positive behavior',
  'other': 'Other',
};

const severityLabels = <String, String>{'low': 'Low', 'medium': 'Medium', 'high': 'High'};

class Analysis {
  Analysis({required this.status, required this.label, this.category, this.severity, this.confidence});

  factory Analysis.fromJson(Map<String, dynamic> j) => Analysis(
        status: j['status'] as String,
        label: j['label'] as String? ?? '',
        category: j['category'] as String?,
        severity: j['severity'] as String?,
        confidence: (j['confidence'] as num?)?.toDouble(),
      );

  /// pending | done | failed | disabled
  final String status;
  final String label;
  final String? category;
  final String? severity;
  final double? confidence;

  bool get isDone => status == 'done';
}

class Anecdote {
  Anecdote({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.authorId,
    required this.occurredAt,
    required this.description,
    required this.status,
    required this.analysis,
    this.section,
    this.author,
    this.location,
    this.actionTaken,
    this.categoryOverride,
    this.severityOverride,
    this.resolutionNote,
    this.resolvedAt,
  });

  factory Anecdote.fromJson(Map<String, dynamic> j) {
    final student = j['student'] as Map<String, dynamic>?;
    return Anecdote(
      id: j['id'] as String,
      studentId: j['student_id'] as int,
      studentName: (student?['name'] as String?) ?? 'Student #${j['student_id']}',
      section: j['section'] as String?,
      authorId: j['author_id'] as int,
      author: j['author'] as String?,
      occurredAt: DateTime.parse(j['occurred_at'] as String).toLocal(),
      location: j['location'] as String?,
      description: j['description'] as String,
      actionTaken: j['action_taken'] as String?,
      categoryOverride: j['category_override'] as String?,
      severityOverride: j['severity_override'] as String?,
      status: j['status'] as String,
      resolutionNote: j['resolution_note'] as String?,
      resolvedAt: j['resolved_at'] == null ? null : DateTime.parse(j['resolved_at'] as String).toLocal(),
      analysis: Analysis.fromJson(j['analysis'] as Map<String, dynamic>),
    );
  }

  final String id;
  final int studentId;
  final String studentName;
  final String? section;
  final int authorId;
  final String? author;
  final DateTime occurredAt;
  final String? location;
  final String description;
  final String? actionTaken;
  final String? categoryOverride;
  final String? severityOverride;
  final String status;
  final String? resolutionNote;
  final DateTime? resolvedAt;
  final Analysis analysis;

  bool get isOpen => status == 'open';

  /// Human override if set, otherwise the AI category.
  String? get finalCategory => categoryOverride ?? analysis.category;
  String? get finalSeverity => severityOverride ?? analysis.severity;
}

class Paged<T> {
  Paged(this.items, this.currentPage, this.lastPage, this.total);

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}
