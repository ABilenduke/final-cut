/**
 * Safely serialize a value for embedding in a `<script type="application/ld+json">` tag.
 *
 * `JSON.stringify` does not escape `</script>` or similar sequences that would
 * break out of the script block and open an XSS vector. This utility replaces
 * dangerous characters after serialization so the output is safe for `innerHTML`.
 */
export function safeJsonLd(value: unknown): string {
  const json = JSON.stringify(value) ?? 'null'
  // Replace characters that can break out of a <script> context:
  // - `<`   → `\u003c` prevents `</script>` injection and other tag openings
  // - `>` and `&` are escaped as defense-in-depth (the `<` escape already
  //   blocks `</script>`; these neutralize comment framing / entity ambiguity)
  // - U+2028 / U+2029 are valid JSON but act as line terminators in JS
  return json
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026')
    .replace(/\u2028/g, '\\u2028')
    .replace(/\u2029/g, '\\u2029')
}
