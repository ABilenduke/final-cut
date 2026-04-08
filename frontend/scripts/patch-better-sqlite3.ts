/**
 * Patch better-sqlite3 with a pure JS mock for Deno compatibility.
 *
 * better-sqlite3 ships a native Node.js addon (.node binary) that fails under
 * Deno's Node compatibility layer. @nuxt/content requires this package at build
 * time but the Nuxt dev server and tests don't directly exercise SQLite queries
 * (content is compiled to static JSON at build). This script replaces the native
 * entry point with a no-op mock so that `deno run` and `vitest` work correctly.
 *
 * The patch is idempotent and runs as part of `deno task postinstall`.
 */

import { walk } from 'jsr:@std/fs/walk'
import { join } from 'jsr:@std/path'

const MOCK = `"use strict";
class Statement {
  run() { return { changes: 0, lastInsertRowid: 0 }; }
  get() { return undefined; }
  all() { return []; }
  iterate() { return [][Symbol.iterator](); }
  bind() { return this; }
  columns() { return []; }
  safeIntegers() { return this; }
  pluck() { return this; }
  expand() { return this; }
  raw() { return this; }
}
class Database {
  pragma() { return []; }
  prepare() { return new Statement(); }
  close() {}
  transaction(fn) { return fn; }
  function() {}
  aggregate() {}
  table() {}
  loadExtension() {}
  defaultSafeIntegers() { return this; }
  unsafeMode() { return this; }
}
module.exports = Database;
`

// Find better-sqlite3 in node_modules (Deno uses a nested .deno/ layout)
const nodeModules = join(Deno.cwd(), 'node_modules')

for await (const entry of walk(nodeModules, {
  match: [/better-sqlite3/],
  maxDepth: 5,
})) {
  if (entry.isFile && entry.name === 'index.js' && entry.path.includes('better-sqlite3/lib/')) {
    const content = await Deno.readTextFile(entry.path)
    if (content.includes('node_module_register') || content.includes('DEFAULT_ADDON') || !content.includes('class Database')) {
      await Deno.writeTextFile(entry.path, MOCK)
      console.log(`Patched better-sqlite3: ${entry.path}`)
    } else {
      console.log(`better-sqlite3 already patched: ${entry.path}`)
    }
  }
}
