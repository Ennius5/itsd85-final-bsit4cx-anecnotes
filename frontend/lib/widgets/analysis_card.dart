import 'package:flutter/material.dart';

import '../models/models.dart';

const aiColor = Color(0xFF6D28D9);

/// Shows the AI suggestion, always labeled as machine-generated (T6).
class AnalysisCard extends StatelessWidget {
  const AnalysisCard({super.key, required this.anecdote});

  final Anecdote anecdote;

  @override
  Widget build(BuildContext context) {
    final a = anecdote.analysis;
    final theme = Theme.of(context);

    final List<Widget> lines;
    if (a.isDone) {
      lines = [
        Text('Category: ${categoryLabels[a.category] ?? a.category}'),
        Text('Severity: ${severityLabels[a.severity] ?? a.severity}'
            '${a.confidence != null ? ' · Confidence ${a.confidence!.toStringAsFixed(2)}' : ''}'),
        if (anecdote.categoryOverride != null || anecdote.severityOverride != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              'Corrected by staff: '
              '${[
                if (anecdote.categoryOverride != null) categoryLabels[anecdote.categoryOverride],
                if (anecdote.severityOverride != null) '${severityLabels[anecdote.severityOverride]} severity',
              ].join(' · ')}',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
      ];
    } else {
      lines = [Text(a.label)];
    }

    return Card(
      key: const Key('analysis-card'),
      shape: RoundedRectangleBorder(
        side: const BorderSide(color: aiColor, width: 1.5),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              const Icon(Icons.auto_awesome, color: aiColor, size: 18),
              const SizedBox(width: 6),
              Text(
                a.isDone ? 'AI suggestion (machine-generated)' : 'AI analysis',
                style: theme.textTheme.titleSmall?.copyWith(color: aiColor, fontWeight: FontWeight.bold),
              ),
            ]),
            const SizedBox(height: 6),
            ...lines,
          ],
        ),
      ),
    );
  }
}

/// Small chip for list rows: the final category, or the analysis state.
class CategoryChip extends StatelessWidget {
  const CategoryChip({super.key, required this.anecdote});

  final Anecdote anecdote;

  @override
  Widget build(BuildContext context) {
    final category = anecdote.finalCategory;
    final severity = anecdote.finalSeverity;
    final String text;
    if (category != null) {
      text = '${categoryLabels[category] ?? category}${severity != null ? ' · ${severityLabels[severity]}' : ''}';
    } else {
      text = switch (anecdote.analysis.status) {
        'pending' => 'Analysis pending',
        'failed' => 'Analysis unavailable',
        _ => 'No category',
      };
    }
    final high = severity == 'high';
    return Text(
      text,
      style: TextStyle(fontSize: 12, color: high ? Colors.red.shade800 : Colors.grey.shade700, fontWeight: high ? FontWeight.bold : null),
    );
  }
}
