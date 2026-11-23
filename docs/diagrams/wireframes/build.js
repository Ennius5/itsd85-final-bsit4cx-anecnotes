// Low-fidelity wireframes for Anecnotes (M2).
// Run: node docs/diagrams/wireframes/build.js   -> writes one .svg per screen next to this file.
// No dependencies. Edit the SCREENS list to change a wireframe.

const fs = require("fs");
const path = require("path");

const C = { ink: "#222", mid: "#777", line: "#bbb", fill: "#eee", accent: "#1F3864", warn: "#b45309", ai: "#6d28d9", bg: "#fff" };
const esc = (s) => String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

function text(x, y, s, o = {}) {
  const size = o.size || 13, weight = o.bold ? 700 : 400, fill = o.color || C.ink, anchor = o.anchor || "start";
  return `<text x="${x}" y="${y}" font-family="Arial, sans-serif" font-size="${size}" font-weight="${weight}" fill="${fill}" text-anchor="${anchor}">${esc(s)}</text>`;
}
function rect(x, y, w, h, o = {}) {
  return `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${o.r ?? 6}" fill="${o.fill || C.bg}" stroke="${o.stroke || C.line}" stroke-width="${o.sw || 1}"${o.dash ? ' stroke-dasharray="4 3"' : ""}/>`;
}

// ---- components (each returns [svg, height]) ----
const W = {
  appbar: (s, x, y, w) => [rect(x, y, w, 52, { r: 0, fill: C.accent, stroke: C.accent }) + text(x + 16, y + 32, s.title, { size: 17, bold: true, color: "#fff" }) + (s.right ? text(x + w - 16, y + 32, s.right, { color: "#fff", anchor: "end" }) : ""), 60],
  h: (s, x, y) => [text(x, y + 18, s.text, { size: 15, bold: true }), 28],
  p: (s, x, y) => [text(x, y + 14, s.text, { color: s.color || C.mid, size: s.size || 12 }), 22],
  input: (s, x, y, w) => {
    const h = s.lines ? 18 * s.lines + 16 : 40;
    return [text(x, y + 12, s.label, { size: 11, color: C.mid }) + rect(x, y + 18, w, h) + text(x + 10, y + 42, s.value || "", { color: s.value ? C.ink : C.line }), h + 28];
  },
  button: (s, x, y, w) => {
    const bw = s.w || w, primary = s.kind !== "secondary";
    return [rect(x, y, bw, 42, { fill: primary ? C.accent : C.bg, stroke: C.accent }) + text(x + bw / 2, y + 26, s.text, { anchor: "middle", bold: true, color: primary ? "#fff" : C.accent }), 52];
  },
  buttons: (s, x, y, w) => {
    const gap = 10, bw = (w - gap * (s.items.length - 1)) / s.items.length;
    return [s.items.map((b, i) => W.button({ ...b, w: bw }, x + i * (bw + gap), y, bw)[0]).join(""), 52];
  },
  banner: (s, x, y, w) => {
    const col = s.kind === "ai" ? C.ai : s.kind === "warn" ? C.warn : C.accent;
    const lines = [].concat(s.text);
    const h = 16 + lines.length * 18;
    return [rect(x, y, w, h, { fill: "#fafafa", stroke: col, sw: 1.5 }) + lines.map((l, i) => text(x + 12, y + 22 + i * 18, l, { size: 12, color: i === 0 ? col : C.ink, bold: i === 0 })).join(""), h + 10];
  },
  row: (s, x, y, w) => [rect(x, y, w, 56) + text(x + 12, y + 23, s.title, { bold: true }) + text(x + 12, y + 42, s.sub || "", { size: 11, color: C.mid }) + (s.right ? text(x + w - 12, y + 32, s.right, { anchor: "end", size: 12, color: s.rightColor || C.mid, bold: !!s.rightColor }) : ""), 62],
  chips: (s, x, y) => {
    let cx = x, out = "";
    for (const c of s.items) { const cw = c.length * 7 + 22; out += rect(cx, y, cw, 26, { r: 13, fill: c === s.active ? C.accent : C.fill, stroke: c === s.active ? C.accent : C.line }) + text(cx + cw / 2, y + 17, c, { size: 11, anchor: "middle", color: c === s.active ? "#fff" : C.ink }); cx += cw + 8; }
    return [out, 36];
  },
  seats: (s, x, y, w) => {
    const { rows, cols, marks = {} } = s, gap = 6, sw = (w - gap * (cols - 1)) / cols, sh = 38;
    let out = rect(x + w / 2 - 50, y, 100, 18, { r: 3, fill: C.fill }) + text(x + w / 2, y + 13, "TEACHER'S TABLE", { size: 9, anchor: "middle", color: C.mid });
    const y0 = y + 28, colors = { P: "#e8f5e9", A: "#fde2e1", L: "#fff4d6", E: "#e3ecfa" };
    for (let r = 0; r < rows; r++) for (let c = 0; c < cols; c++) {
      const k = `${r},${c}`, m = marks[k] || "P", empty = (s.empty || []).includes(k);
      const sx = x + c * (sw + gap), sy = y0 + r * (sh + gap);
      out += empty ? rect(sx, sy, sw, sh, { r: 4, dash: true, fill: C.bg }) : rect(sx, sy, sw, sh, { r: 4, fill: colors[m] }) + text(sx + sw / 2, sy + 16, `S${r * cols + c + 1}`, { size: 9, anchor: "middle", color: C.mid }) + text(sx + sw / 2, sy + 31, m, { size: 12, anchor: "middle", bold: true });
    }
    return [out, 28 + rows * (sh + gap) + 4];
  },
  table: (s, x, y, w) => {
    const cw = w / s.cols.length; let out = rect(x, y, w, 30, { r: 0, fill: C.fill });
    s.cols.forEach((c, i) => (out += text(x + 10 + i * cw, y + 20, c, { size: 11, bold: true })));
    s.rows.forEach((r, j) => { out += rect(x, y + 30 + j * 30, w, 30, { r: 0 }); r.forEach((c, i) => (out += text(x + 10 + i * cw, y + 50 + j * 30, c, { size: 11, color: String(c).startsWith("!") ? C.warn : C.ink }))); });
    return [out.replace(/>!/g, ">"), 30 + s.rows.length * 30 + 12];
  },
  bars: (s, x, y, w) => {
    const h = 120, bw = w / s.values.length - 8, max = Math.max(...s.values);
    let out = text(x, y + 12, s.label, { size: 11, color: C.mid }) + rect(x, y + 20, w, h + 30, { fill: C.bg });
    s.values.forEach((v, i) => { const bh = (v / max) * (h - 10); out += rect(x + 6 + i * (bw + 8), y + 20 + h - bh, bw, bh, { r: 2, fill: "#c7d2e4", stroke: "#9fb0cc" }) + text(x + 6 + i * (bw + 8) + bw / 2, y + 14 + h - bh, String(v), { size: 10, anchor: "middle", bold: true }) + text(x + 6 + i * (bw + 8) + bw / 2, y + h + 40, s.labels[i], { size: 9, anchor: "middle", color: C.mid }); });
    return [out, h + 62];
  },
  tabbar: (s, x, y, w) => {
    const tw = w / s.items.length;
    return [rect(x, y, w, 56, { r: 0, fill: "#fafafa" }) + s.items.map((t, i) => text(x + tw * i + tw / 2, y + 33, t, { size: 11, anchor: "middle", bold: t === s.active, color: t === s.active ? C.accent : C.mid })).join(""), 56];
  },
  note: (s, x, y, w) => [text(x, y + 12, "Note: " + s.text, { size: 10, color: C.warn }), 20],
  gap: (s) => ["", s.h || 10],
};

function render(screen) {
  const web = screen.kind === "web";
  const FW = web ? 1024 : 360, FH = screen.height || (web ? 640 : 720), pad = 16;
  const title = 30;
  let body = "";
  let y = title + 10;
  const ox = 10, inner = FW;
  body += rect(ox, y, inner, FH, { r: web ? 6 : 24, stroke: "#555", sw: 2 });
  let cy = y + (web ? 0 : 14);
  if (!web) body += rect(ox + inner / 2 - 30, y + 6, 60, 5, { r: 3, fill: "#ccc", stroke: "#ccc" });
  // optional left nav for web
  let cx = ox, cw = inner;
  for (const el of screen.top || []) { const [svg, h] = W[el.t](el, ox, cy, inner); body += svg; cy += h; }
  if (web && screen.nav) {
    const navW = 190;
    body += rect(ox, cy - 8, navW, FH - (cy - y) + 8, { r: 0, fill: "#f6f7fa" });
    screen.nav.forEach((n, i) => (body += text(ox + 18, cy + 22 + i * 32, n, { bold: n === screen.navActive, color: n === screen.navActive ? C.accent : C.ink })));
    cx = ox + navW; cw = inner - navW;
  }
  cy += 6;
  for (const el of screen.body) { const [svg, h] = W[el.t](el, cx + pad, cy, cw - pad * 2); body += svg; cy += h; }
  for (const el of screen.bottom || []) { const [svg] = W[el.t](el, ox, y + FH - 56, inner); body += svg; }
  const svgW = FW + 20, svgH = FH + title + 30;
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${svgW}" height="${svgH}" viewBox="0 0 ${svgW} ${svgH}">` +
    `<rect width="100%" height="100%" fill="#fff"/>` + text(10, 22, screen.caption, { size: 14, bold: true }) + body + `</svg>`;
}

const teacherTabs = { t: "tabbar", items: ["Home", "Roll call", "Anecdotes", "Sync"] };
const guidanceTabs = { t: "tabbar", items: ["Flags", "Anecdotes", "Students", "Stats"] };

const SCREENS = [
  {
    file: "01-login", caption: "01 · Sign in (all roles) — US-01",
    body: [
      { t: "gap", h: 70 }, { t: "h", text: "Anecnotes" }, { t: "p", text: "Valencia National High School" }, { t: "gap", h: 20 },
      { t: "input", label: "Email", value: "adviser@demo.anecnotes.test" },
      { t: "input", label: "Password", value: "••••••••••" },
      { t: "button", text: "Sign in" },
      { t: "p", text: "Forgot your password? Ask the school admin." },
      { t: "gap", h: 30 },
      { t: "banner", kind: "info", text: ["Offline sign-in", "After the first sign-in, the app opens offline", "with data saved on this phone (encrypted)."] },
    ],
  },
  {
    file: "02-teacher-home", caption: "02 · Teacher home — US-07, US-10, US-18",
    top: [{ t: "appbar", title: "Good morning, Ms. Santos", right: "⋮" }],
    body: [
      { t: "banner", kind: "warn", text: ["Offline · 4 records waiting to sync", "Last synced today 6:52 AM.  [Sync now]"] },
      { t: "h", text: "My advisory section" },
      { t: "row", title: "Grade 8 – Rizal", sub: "34 students · Roll call not taken today", right: "Take roll call ›", rightColor: C.accent },
      { t: "h", text: "Quick actions" },
      { t: "buttons", items: [{ text: "+ Anecdote" }, { text: "Find student", kind: "secondary" }] },
      { t: "h", text: "Recent anecdotes" },
      { t: "row", title: "Dela Cruz, Juan", sub: "Canteen · today 10:15", right: "AI: aggression · med" },
      { t: "row", title: "Garcia, Ana", sub: "Room 204 · yesterday", right: "Analysis pending" },
    ],
    bottom: [{ ...teacherTabs, active: "Home" }],
  },
  {
    file: "03-roll-call", caption: "03 · Seat-plan roll call — US-05, US-07, US-08",
    top: [{ t: "appbar", title: "Roll call · 8–Rizal", right: "AM" }],
    body: [
      { t: "p", text: "Tap a seat: Present → Absent → Late → Excused", color: C.ink },
      { t: "seats", rows: 6, cols: 6, marks: { "0,2": "A", "1,4": "L", "3,1": "A", "4,5": "E" }, empty: ["5,4", "5,5"] },
      { t: "p", text: "Present 30 · Absent 2 · Late 1 · Excused 1 · Unseated 0" },
      { t: "button", text: "Save roll call" },
      { t: "banner", kind: "info", text: ["Correction window: 15 min", "Editable until 7:46 AM (phone time),", "then only an admin can change it."] },
    ],
    bottom: [{ ...teacherTabs, active: "Roll call" }],
  },
  {
    file: "04-new-anecdote", caption: "04 · Write an anecdote (≤ 5 taps) — US-10",
    top: [{ t: "appbar", title: "New anecdote", right: "Save" }],
    body: [
      { t: "input", label: "Student *", value: "Dela Cruz, Juan  (8–Rizal)" },
      { t: "input", label: "When *", value: "Today, 10:15 AM" },
      { t: "chips", items: ["Classroom", "Canteen", "Hallway", "Other"], active: "Canteen" },
      { t: "input", label: "What happened? *", value: "Pushed a classmate during recess and…", lines: 5 },
      { t: "input", label: "Action taken (optional)", value: "", lines: 2 },
      { t: "button", text: "Save anecdote" },
      { t: "p", text: "Saved on this phone first; sent when online." },
    ],
  },
  {
    file: "05-anecdote-detail", caption: "05 · Anecdote + AI suggestion — US-11 to US-13",
    top: [{ t: "appbar", title: "Anecdote", right: "Edit" }],
    body: [
      { t: "row", title: "Dela Cruz, Juan · 8–Rizal", sub: "By M. Santos · Canteen · Nov 23, 10:15 AM" },
      { t: "p", text: "Pushed a classmate during recess and called him names.", color: C.ink, size: 13 },
      { t: "p", text: "Action taken: Talked to both students." },
      { t: "banner", kind: "ai", text: ["AI suggestion (machine-generated)", "Category: Aggression / bullying", "Severity: Medium · Confidence 0.82", "Model: llama-3.1-8b-instant via Groq"] },
      { t: "buttons", items: [{ text: "Change category", kind: "secondary" }, { text: "Resolve", kind: "secondary" }] },
      { t: "p", text: "Status: Open" },
      { t: "note", text: "Resolve is shown to Guidance only." },
    ],
  },
  {
    file: "06-guidance-flags", caption: "06 · Behavior flags list (Guidance) — US-14",
    top: [{ t: "appbar", title: "Behavior flags", right: "Filter" }],
    body: [
      { t: "chips", items: ["Pending (3)", "Confirmed", "Dismissed"], active: "Pending (3)" },
      { t: "row", title: "Dela Cruz, Juan · 8–Rizal", sub: "Aggression · 3 anecdotes in 30 days", right: "Count rule", rightColor: C.warn },
      { t: "row", title: "Reyes, Mark · 9–Mabini", sub: "Wellbeing · 1 anecdote, high severity", right: "High severity", rightColor: "#b91c1c" },
      { t: "row", title: "Lim, Carla · 8–Rizal", sub: "Similar wording from 2 teachers", right: "Similar text", rightColor: C.warn },
      { t: "gap", h: 6 },
      { t: "banner", kind: "info", text: ["Flags are prompts to look, not findings.", "A counselor confirms or dismisses each one."] },
    ],
    bottom: [{ ...guidanceTabs, active: "Flags" }],
  },
  {
    file: "07-flag-detail", caption: "07 · Flag detail with AI summary — US-14, US-15",
    top: [{ t: "appbar", title: "Flag · Dela Cruz, Juan" }],
    body: [
      { t: "p", text: "Aggression / bullying · Oct 27 – Nov 25 · 3 anecdotes", color: C.ink },
      { t: "banner", kind: "ai", text: ["AI-generated summary — verify against the records", "Three incidents of physical aggression toward", "classmates in four weeks, mostly at recess [1][2][3]."] },
      { t: "row", title: "[1] Nov 23 · Canteen · M. Santos", sub: "Pushed a classmate during recess…" },
      { t: "row", title: "[2] Nov 12 · Field · R. Cruz", sub: "Hit a classmate with a ball on purpose…" },
      { t: "row", title: "[3] Oct 29 · Hallway · M. Santos", sub: "Shoved a Grade 7 student…" },
      { t: "input", label: "Review note *", value: "Scheduled counseling for Nov 26.", lines: 2 },
      { t: "buttons", items: [{ text: "Confirm" }, { text: "Dismiss", kind: "secondary" }] },
    ],
  },
  {
    file: "08-admin-sf1-import", caption: "08 · Admin: SF1 import with preview (web) — US-04", kind: "web",
    top: [{ t: "appbar", title: "Anecnotes · Admin", right: "admin@demo.anecnotes.test ▾" }],
    nav: ["Dashboard", "Sections", "Students", "Import SF1", "Accounts", "Attendance", "Audit log", "AI usage"], navActive: "Import SF1",
    body: [
      { t: "h", text: "Import SF1 class list" },
      { t: "buttons", items: [{ text: "SY 2026–2027 ▾", kind: "secondary" }, { text: "Adviser: M. Santos ▾", kind: "secondary" }, { text: "Choose SF1 .xlsx" }] },
      { t: "banner", kind: "info", text: ["Preview — nothing is saved yet", "Grade 8 – Rizal · 38 to add · 2 already present (matched by LRN) · 1 error"] },
      { t: "table", cols: ["Row", "LRN", "Name", "Sex", "Birthdate", "Result"], rows: [["8", "123456789012", "Dela Cruz, Juan S.", "M", "2012-04-09", "Add"], ["9", "123456789013", "Garcia, Ana", "F", "2012-07-21", "Already present"], ["17", "12345678", "Lim, Carla", "F", "2012-01-30", "!LRN must be 12 digits"]] },
      { t: "buttons", items: [{ text: "Cancel", kind: "secondary" }, { text: "Import 38 students" }] },
    ],
  },
  {
    file: "09-dashboard", caption: "09 · Statistics dashboard (web) — US-16", kind: "web",
    top: [{ t: "appbar", title: "Anecnotes · Guidance", right: "guidance@demo.anecnotes.test ▾" }],
    nav: ["Dashboard", "Behavior flags", "Anecdotes", "Students", "Statistics"], navActive: "Dashboard",
    body: [
      { t: "buttons", items: [{ text: "Pending flags: 3", kind: "secondary" }, { text: "Open anecdotes: 18", kind: "secondary" }, { text: "Attendance (Nov): 94%", kind: "secondary" }] },
      { t: "bars", label: "Anecdotes per category · November", values: [4, 9, 6, 7, 3, 2, 5, 1], labels: ["Tardy", "Disrupt", "Defiance", "Aggress", "Academic", "Wellbeing", "Positive", "Other"] },
      { t: "bars", label: "Attendance rate per section · November (%)", values: [94, 91, 97, 89], labels: ["8–Rizal", "8–Bonifacio", "9–Mabini", "9–Luna"] },
    ],
    height: 700,
  },
];

for (const s of SCREENS) fs.writeFileSync(path.join(__dirname, s.file + ".svg"), render(s));
console.log(`wrote ${SCREENS.length} wireframes`);
