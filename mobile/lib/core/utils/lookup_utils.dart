/// Giống extension: lấy từ đầu tiên từ selection / câu.
String lookupTermFromText(String text) {
  final trimmed = text
      .replaceAll(RegExp(r'[\u200B-\u200D\uFEFF]'), '')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();
  final first = trimmed.split(RegExp(r'\s+')).first;
  final word = first.replaceAll(RegExp(r"^[^a-zA-Z]+|[^a-zA-Z''-]+$"), '');
  return word.toLowerCase();
}

bool isValidLookupInput(String text) {
  final t = text.trim();
  if (t.isEmpty || t.length > 400) return false;
  if (!RegExp(r'[a-zA-Z]').hasMatch(t)) return false;
  final word = lookupTermFromText(t);
  return word.isNotEmpty && word.length <= 48;
}
