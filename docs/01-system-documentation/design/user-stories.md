# User Stories

**System:** Anecnotes · **Milestone:** M2 (Design) · **Status:** draft, 2026-11-23

Roles: **Admin**, **Adviser/Teacher** (an adviser is a teacher assigned to a section), **Guidance** (counselor).
Priority: **M** = MVP (must ship by M4), **S** = should, **C** = could (only if time allows).

Each story has acceptance criteria (AC) written so they can become API tests (Pest), client tests or UAT tasks.

---

## Authentication and accounts

### US-01 · Sign in (all roles) · M
As a staff member, I want to sign in with my email and password so that only I can see the records I am allowed to see.

- AC1: Valid credentials return a token; the app opens the home screen for my role.
- AC2: A wrong password returns `401` with a generic message (it does not reveal whether the email exists).
- AC3: After 5 failed attempts in 1 minute from the same email and IP, logins are refused with `429` for 1 minute.
- AC4: A deactivated or locked account cannot sign in (`403`).
- AC5: Signing out revokes the token and, on Android, wipes the local database.

### US-02 · Manage accounts (Admin) · M
As an admin, I want to create, lock and deactivate accounts so that only current staff can reach student records.

- AC1: Admin creates an account with name, email, role and a temporary password.
- AC2: Admin can lock (temporary) or deactivate (permanent) an account; that user's tokens are revoked at once.
- AC3: Non-admins receive `403` on every `/users` write endpoint.
- AC4: Every create, lock and deactivate is written to the audit log.

### US-03 · Change my password (all roles) · S
As a staff member, I want to change my temporary password so that only I know it.

- AC1: The new password has at least 10 characters and differs from the current one.
- AC2: Other sessions are signed out after the change.

---

## School year setup

### US-04 · Import SF1 class list (Admin) · M
As an admin, I want to import an SF1 class list and assign an adviser so that each school year is set up quickly.

- AC1: Upload accepts the DepEd SF1 `.xlsx` layout; LRN, name, sex and birthdate are read.
- AC2: A preview shows rows to add, rows already present (matched by LRN) and rows with errors, before anything is saved.
- AC3: Confirming creates the section (if new), students (if new) and enrollments in one transaction.
- AC4: Re-importing the same file creates no duplicates.
- AC5: A file with an invalid LRN (not 12 digits) is rejected row by row, with the row number shown.

### US-05 · Seat plan (Admin, Adviser) · M
As an adviser, I want to arrange my students on a seat-plan grid so that roll call matches the classroom.

- AC1: A section has a grid size (rows × columns); each enrolled student occupies at most one seat.
- AC2: Two students cannot share a seat (`422`).
- AC3: Unseated students are listed below the grid and can still be marked.

### US-06 · Manage sections and students (Admin) · M
As an admin, I want to create, edit and remove sections and students so that records stay accurate when students transfer.

- AC1: Full create, read, update, delete on sections and students, with pagination, search (name or LRN) and sorting.
- AC2: A transferred student keeps their history; the enrollment is marked `transferred`, not deleted.
- AC3: Deleting a student with anecdotes or attendance is a soft delete, logged in the audit log.

---

## Attendance

### US-07 · Seat-plan roll call (Adviser) · M
As an adviser, I want to take attendance from a seat plan on my phone, even offline, so that roll call takes under a minute.

- AC1: Everyone starts as **present**; tapping a seat cycles present → absent → late → excused.
- AC2: Saving takes at most 5 taps beyond the seats I change (open section → roll call → change seats → save).
- AC3: Works with no connection; the session is queued and synced when online, and the queue shows a pending count.
- AC4: Only one session per section per day per period; a second save edits the first.

### US-08 · 15-minute correction window (Adviser, Admin) · M
As an adviser, I want to correct a mistake right after roll call, and as an admin I want later changes to go through me, so that attendance records can be trusted.

- AC1: The adviser can edit a session for 15 minutes after it was first saved, measured by the phone's clock.
- AC2: The server stores both the device time and the server receipt time for every save and edit.
- AC3: After the window, adviser edits are refused (offline: the app blocks them; online: `403`), and only an admin can edit, with a required reason.
- AC4: If the device time is more than 10 minutes ahead of or behind the server time when online, the record is marked `clock_skew` for admin review.
- AC5: Every edit after the first save is written to the audit log with old and new values.

### US-09 · Export SF2 (Adviser, Admin) · M
As an adviser, I want to export SF2 attendance for a month so that I stop copying it by hand.

- AC1: Export produces an `.xlsx` in the SF2 layout for one section and one month.
- AC2: Daily marks, monthly totals per student and the summary block match the database.
- AC3: Only the section's adviser and admins can export it.

---

## Anecdotal records

### US-10 · Write an anecdote fast (Teacher) · M
As a teacher, I want to write an anecdote in 5 taps or fewer, even offline, so that I record incidents as they happen.

- AC1: From home: tap student (or search) → New anecdote → type text → (optional location) → Save.
- AC2: Required: student, date/time (defaults to now), description. Optional: location, action taken.
- AC3: Works offline; the record gets a client-generated UUID, so syncing twice never creates a duplicate.
- AC4: Any teacher may write an anecdote about any student in the current school year.

### US-11 · Edit my own anecdote (Teacher) · M
As a teacher, I want to edit or delete my own anecdote so that I can fix mistakes.

- AC1: Authors can edit their own anecdotes; others get `403`.
- AC2: A resolved anecdote can no longer be edited by its author.
- AC3: Editing the description re-runs AI analysis; the old analysis is kept for the audit trail.

### US-12 · See AI category and severity (Teacher, Guidance) · M
As a teacher, I want to see the AI-suggested category and severity, labeled as machine-generated, so that I can correct it.

- AC1: After sync, the anecdote shows category, severity and confidence with the label "AI suggestion".
- AC2: The teacher or counselor can override the category and severity; the override is stored next to the AI value, not over it.
- AC3: If analysis failed or is pending, the anecdote still shows and reads "Analysis pending"; nothing blocks saving.

### US-13 · Resolve an anecdote (Guidance) · M
As a counselor, I want to resolve an anecdote with a note so that everyone knows it was handled.

- AC1: Guidance can mark an anecdote resolved with a required resolution note.
- AC2: Resolved anecdotes show who resolved them and when.

---

## Repeated-behavior detection

### US-14 · Review behavior flags (Guidance) · M
As a counselor, I want a list of students flagged for repeated behavior so that I can confirm or dismiss each flag and step in early.

- AC1: A flag is raised when a student has 3 or more anecdotes of the same category within 30 days (counted on the final category: override if present, otherwise AI).
- AC2: Anecdotes with very similar wording (pg_trgm similarity ≥ 0.35) from different teachers are grouped into the same flag even if the AI chose different categories.
- AC3: Any **high**-severity anecdote raises a flag immediately, regardless of count.
- AC4: The list can be filtered by status (pending, confirmed, dismissed), section and category, and sorted by newest.
- AC5: Confirming or dismissing requires a note and is audit-logged. The system never takes disciplinary action on its own.

### US-15 · AI summary of a flagged student (Guidance) · M
As a counselor, I want an AI summary of a flagged student's anecdotes so that I can prepare for counseling.

- AC1: The flag page shows a short summary of the linked anecdotes, labeled "AI-generated, verify against the records".
- AC2: The summary is built from redacted text; names appear only after the server restores them for display.
- AC3: Each sentence in the summary can be traced to the anecdotes listed below it.

---

## Statistics and administration

### US-16 · Statistics dashboard (Admin, Guidance, Adviser) · S
As an admin or counselor, I want counts of attendance and anecdotes by section, category and month so that I can see trends.

- AC1: Advisers see only their sections; Guidance and Admin see all.
- AC2: Charts: attendance rate per section per month; anecdotes per category per month; open versus resolved flags.

### US-17 · Audit log (Admin) · S
As an admin, I want to see who changed what so that misuse can be traced.

- AC1: The log lists user, action, record, old and new values, IP and time, with filters by user and date.
- AC2: The log is read-only for everyone, admins included.

### US-18 · Sync status (Teacher, Adviser) · M
As a teacher using mobile data I pay for, I want to see what is waiting to sync and to sync on demand so that I control my data use.

- AC1: The app shows the number of unsynced records and the last successful sync time.
- AC2: Sync runs automatically on Wi-Fi; on mobile data it runs when I tap "Sync now" (setting).
- AC3: Each sync sends only records changed since the last sync.

---

## Out of scope (from the approved proposal)

Student and parent accounts, grades, biometrics, automatic disciplinary decisions, offline use of the web app, and iOS.
