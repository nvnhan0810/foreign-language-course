import 'package:flutter/material.dart';

class GameTopbar extends StatelessWidget {
  const GameTopbar({
    super.key,
    required this.onClose,
    this.title,
    this.centerWidget,
    this.actions,
    this.bottomWidget,
  });

  final VoidCallback onClose;
  final String? title;
  final Widget? centerWidget;
  final List<Widget>? actions;
  final Widget? bottomWidget;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8.0, vertical: 4.0),
          child: Row(
            children: [
              IconButton(
                icon: const Icon(Icons.close),
                tooltip: 'Close',
                onPressed: onClose,
              ),
              Expanded(
                child: centerWidget ??
                    (title != null
                        ? Text(
                            title!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          )
                        : const SizedBox.shrink()),
              ),
              if (actions != null)
                ...actions!
              else
                const SizedBox(width: 48), // Balance the close button if no actions
            ],
          ),
        ),
        if (bottomWidget != null) bottomWidget!,
      ],
    );
  }
}
