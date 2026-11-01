<?php
if (!defined('LCCT_AI_INTERNAL')) { http_response_code(404); exit; }
return json_decode(<<<'CATALOG'
{
  "welcome": {
    "id": "welcome",
    "title": "Getting started",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "help",
      "hello",
      "hi",
      "start",
      "how does",
      "paano",
      "tulong",
      "helps",
      "hellos",
      "starts",
      "paanos",
      "tulongs"
    ],
    "route": "guidance/staff.php",
    "steps": [
      "Open Staff workspace to find your role-specific work.",
      "Choose a topic here for instructions, or describe the feature you need.",
      "Use the report builder for local previews and exports.",
      "Use existing forms to save changes; the assistant cannot change business records."
    ],
    "note": "One assistant, three permission profiles. Gemini receives only approved generic help instructions, never your original message or school records."
  },
  "reports": {
    "id": "reports",
    "title": "Prepare a report",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "report",
      "analytics",
      "statistics",
      "summary",
      "csv",
      "pdf",
      "ulat",
      "export",
      "reports",
      "summarys",
      "ulats",
      "exports"
    ],
    "route": "guidance/index.php?view=reports",
    "steps": [
      "Choose a report area permitted for your role.",
      "Choose a period or a valid custom range of at most 366 days.",
      "Open the local preview and check its definitions and suppressed values.",
      "Use CSV or Print / PDF on the same authorized report."
    ],
    "note": "The existing report engine retrieves data locally. Counselors remain limited to their assignments; Operations receives activity reports only. Suppressed values are not zero."
  },
  "planning": {
    "id": "planning",
    "title": "Aggregate planning",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "planning report",
      "aggregate",
      "suppressed",
      "small group",
      "aggregates",
      "suppresseds"
    ],
    "route": "guidance/staff.php?tab=planning",
    "steps": [
      "Open Planning report in Staff workspace.",
      "Review the reporting period and available sections.",
      "Read the definitions and suppression labels before comparing measures.",
      "Export through the local report controls only."
    ],
    "note": "Small groups are suppressed by the existing reporting rules. Operations receives activity-only planning; no student identities or narratives belong in aggregate exports."
  },
  "activities": {
    "id": "activities",
    "title": "Activities and invitations",
    "roles": [
      "head",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "activity",
      "activities",
      "event",
      "invitation",
      "invite",
      "rsvp",
      "activitys",
      "events",
      "invitations",
      "invites",
      "rsvps"
    ],
    "route": "guidance/index.php?view=events",
    "steps": [
      "Open Activities and choose an existing activity or its creation form.",
      "Review dates, venue, capacity and the linked program before publishing.",
      "Use the existing invitations and RSVP controls.",
      "Review attendance separately: an invitation or registration is not proof of attendance."
    ],
    "note": "The assistant does not create activities, send invitations or change attendance. Save only through the existing authorized forms."
  },
  "programs": {
    "id": "programs",
    "title": "Programs and evaluation",
    "roles": [
      "head",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "program",
      "evaluation",
      "evaluate",
      "programs",
      "evaluations",
      "evaluates"
    ],
    "route": "guidance/staff.php?tab=programs",
    "steps": [
      "Use Programs for the program and its activity sessions.",
      "Open Staff workspace > Program planning for administrative plans and reviews.",
      "Record institution-approved objectives, evidence and evaluation through the existing forms.",
      "Use aggregate reports to review delivery without exposing individual records."
    ],
    "note": "Program planning supplements the existing activity/program records; it is not a second student portal."
  },
  "attendance": {
    "id": "attendance",
    "title": "Attendance and QR check-in",
    "roles": [
      "head",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "attendance",
      "qr",
      "check-in",
      "check in",
      "attended",
      "attendances",
      "check-ins",
      "attendeds"
    ],
    "route": "guidance/index.php?view=events",
    "steps": [
      "Open the authorized activity and its attendance controls.",
      "Use the existing QR or manual attendance flow.",
      "Check the participant and activity before confirming attendance.",
      "Review totals and correct mistakes only through authorized controls."
    ],
    "note": "QR camera access needs HTTPS or localhost. Do not paste QR payloads, participant details or access tokens into chat."
  },
  "certificates": {
    "id": "certificates",
    "title": "Certificates and credits",
    "roles": [
      "head",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "certificate",
      "credit",
      "backlog",
      "certificates",
      "credits",
      "backlogs"
    ],
    "route": "guidance/index.php?view=register",
    "steps": [
      "Open the Certificate register.",
      "Check the activity attendance and eligibility rules.",
      "Review pending certificates and credits using the existing controls.",
      "Use authorized issuance/download controls; do not invent attendance or credits."
    ],
    "note": "Registration, attendance and certificate eligibility are different states. The assistant does not issue certificates."
  },
  "resources": {
    "id": "resources",
    "title": "Published resources",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": true,
    "words": [
      "resource",
      "knowledge",
      "article",
      "published",
      "resources",
      "knowledges",
      "articles",
      "publisheds"
    ],
    "route": "guidance/staff.php?tab=knowledge",
    "steps": [
      "Open Published resources.",
      "Search for an approved resource using the existing search.",
      "Check that the resource is appropriate and published before sharing it.",
      "Use the existing resource workflow for any edits."
    ],
    "note": "Resource documents and attachments are not uploaded to Gemini. These instructions do not summarize private documents."
  },
  "settings": {
    "id": "settings",
    "title": "Account and password help",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": false,
    "words": [
      "password",
      "account",
      "device",
      "session",
      "settings",
      "profile",
      "login",
      "passwords",
      "accounts",
      "devices",
      "sessions",
      "profiles",
      "logins"
    ],
    "route": "guidance/index.php?view=settings",
    "steps": [
      "Open Settings from the top-right account menu.",
      "Use the existing password-change controls and enter credentials only there.",
      "Review available account/session controls.",
      "If access is blocked, ask the authorized administrator; never share passwords in chat."
    ],
    "note": "This assistant cannot reset passwords, bypass lockout or claim MFA/device binding is configured."
  },
  "privacy": {
    "id": "privacy",
    "title": "Privacy and assistant limits",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": false,
    "words": [
      "privacy",
      "confidential",
      "secret",
      "api key",
      "credential",
      "token",
      "ignore instructions",
      "ignore rules",
      "system prompt",
      "sql",
      "shell",
      "delete all",
      "privacys",
      "confidentials",
      "secrets",
      "credentials",
      "tokens",
      "shells"
    ],
    "route": "guidance/staff.php",
    "steps": [
      "Do not enter names, case narratives, attachments, credentials or personal information.",
      "Messages are matched to approved topics locally; this module does not save their raw text.",
      "Only reviewed generic instructions can be sent to Gemini.",
      "AI output is advisory text, not authorization, verified facts or clinical advice."
    ],
    "note": "No arbitrary SQL, shell commands, URLs, file access, record changes or clinical decisions are available to the model. Local access logs or host administrators remain outside this module's control."
  },
  "cases": {
    "id": "cases",
    "title": "Assigned requests and cases",
    "roles": [
      "head",
      "counselor"
    ],
    "cloud": false,
    "words": [
      "case",
      "request review",
      "assigned",
      "intake",
      "walk-in",
      "walkin",
      "cases",
      "assigneds",
      "intakes",
      "walk-ins",
      "walkins"
    ],
    "route": "guidance/index.php?view=requests",
    "steps": [
      "Open your authorized Guidance requests.",
      "Select a request you are permitted to access.",
      "Use its existing review and follow-through controls.",
      "Keep clinical documentation in the authorized record, never in chat."
    ],
    "note": "Counselors are restricted to current assignments. The assistant cannot read, summarize, diagnose or decide a case."
  },
  "schedule": {
    "id": "schedule",
    "title": "Scheduling and availability",
    "roles": [
      "head",
      "counselor"
    ],
    "cloud": false,
    "words": [
      "schedule",
      "appointment",
      "availability",
      "reschedule",
      "buffer",
      "waitlist",
      "collision",
      "calendar",
      "schedules",
      "appointments",
      "availabilitys",
      "reschedules",
      "buffers",
      "waitlists",
      "collisions",
      "calendars"
    ],
    "route": "guidance/staff.php?tab=schedule",
    "steps": [
      "Open Scheduling in Staff workspace.",
      "Review your permitted availability rules, blocked time and buffer spacing.",
      "Use existing appointment/rescheduling controls for a valid slot.",
      "Resolve collisions or waitlist items through the authorized workflow."
    ],
    "note": "Existing server checks enforce ownership and scheduling limits. No appointments are created or changed by chat; appointment-purpose text may be student-visible."
  },
  "support": {
    "id": "support",
    "title": "Support plans and progress",
    "roles": [
      "head",
      "counselor"
    ],
    "cloud": false,
    "words": [
      "support plan",
      "goal",
      "progress",
      "care plan",
      "goals"
    ],
    "route": "guidance/staff.php?tab=care",
    "steps": [
      "Open Support plans for an authorized assigned record.",
      "Use the existing plan form for human-agreed goals and actions.",
      "Record progress and evidence only in the authorized form.",
      "Review the existing completion requirements before saving."
    ],
    "note": "Local navigation help only. Gemini is not used for clinical planning, diagnosis, treatment or deciding progress."
  },
  "consent": {
    "id": "consent",
    "title": "Consent records",
    "roles": [
      "head",
      "counselor"
    ],
    "cloud": false,
    "words": [
      "consent",
      "withdraw",
      "expiry",
      "confidentiality",
      "consents",
      "withdraws",
      "expirys",
      "confidentialitys"
    ],
    "route": "guidance/staff.php?tab=consent",
    "steps": [
      "Open Consent records for an authorized record.",
      "Choose a current institution-approved policy version.",
      "Document authority, evidence, state and expiry in the existing form.",
      "Use the withdrawal workflow when appropriate; do not assume old consent remains valid."
    ],
    "note": "This assistant does not determine legal authority or approve information sharing. Keep evidence and personal details in the authorized record."
  },
  "referrals": {
    "id": "referrals",
    "title": "Referral tracking",
    "roles": [
      "head",
      "counselor"
    ],
    "cloud": false,
    "words": [
      "referral",
      "refer",
      "referrals",
      "refers"
    ],
    "route": "guidance/staff.php?tab=referrals",
    "steps": [
      "Open Referrals for an authorized record.",
      "Check the current consent or institution-approved exception workflow.",
      "Use the existing form and record only the authorized minimum information.",
      "Track follow-through using existing status/evidence controls."
    ],
    "note": "Human review and institutional policy govern sharing. The assistant does not choose destinations, approve exceptions or send referrals."
  },
  "outcomes": {
    "id": "outcomes",
    "title": "Voluntary outcomes",
    "roles": [
      "head",
      "counselor"
    ],
    "cloud": false,
    "words": [
      "outcome",
      "rating",
      "wellbeing score",
      "outcomes",
      "ratings"
    ],
    "route": "guidance/staff.php?tab=care",
    "steps": [
      "Use the authorized support workspace for the selected record.",
      "Use voluntary outcome controls only under the institution's policy.",
      "Document consent and evidence in the existing workflow.",
      "Treat reported changes as nondiagnostic; use human professional judgment."
    ],
    "note": "No AI scoring, diagnosis, risk classification or interpretation of an individual outcome is available."
  },
  "continuity": {
    "id": "continuity",
    "title": "Continuity and handover",
    "roles": [
      "head",
      "counselor"
    ],
    "cloud": false,
    "words": [
      "continuity",
      "handover",
      "transfer",
      "continuitys",
      "handovers",
      "transfers"
    ],
    "route": "guidance/staff.php?tab=care",
    "steps": [
      "Open the authorized support workspace.",
      "Use existing continuity/handover controls and current assignment permissions.",
      "Verify the intended authorized recipient before sharing.",
      "Record the approved handover through existing forms."
    ],
    "note": "This assistant cannot transfer assignments, read narratives or generate a clinical handover."
  },
  "urgent": {
    "id": "urgent",
    "title": "Human-led urgent workflow",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": false,
    "words": [
      "urgent",
      "emergency",
      "suicide",
      "self-harm",
      "hurt myself",
      "danger",
      "crisis",
      "urgents",
      "emergencys",
      "suicides",
      "self-harms",
      "dangers"
    ],
    "route": "guidance/staff.php",
    "steps": [
      "Contact authorized Guidance staff promptly and follow the institution's emergency protocol.",
      "If there is immediate danger, contact local emergency services.",
      "Authorized clinical staff can open Urgent review in Staff workspace.",
      "Record actions through the human-led workflow; do not rely on this assistant to assess urgency."
    ],
    "note": "No clinical triage, risk score or emergency decision is made here. Do not send case details to the assistant."
  },
  "governance": {
    "id": "governance",
    "title": "Governance and operational health",
    "roles": [
      "head"
    ],
    "cloud": false,
    "words": [
      "governance",
      "backup",
      "restore",
      "access review",
      "health check",
      "governances",
      "backups",
      "restores"
    ],
    "route": "guidance/staff.php?tab=governance",
    "steps": [
      "Open Governance & health.",
      "Review operational failures and outstanding access/backup/restore checks.",
      "Record verified evidence through existing controls; do not mark incomplete checks as passed.",
      "Coordinate recovery and permission changes with the appropriate owner."
    ],
    "note": "The assistant cannot back up or restore a database, certify recovery, change access, or verify a real backup by conversation."
  },
  "reminders": {
    "id": "reminders",
    "title": "Reminder delivery",
    "roles": [
      "head"
    ],
    "cloud": false,
    "words": [
      "reminder",
      "scheduler",
      "delivery failure",
      "reminders",
      "schedulers"
    ],
    "route": "guidance/staff.php?tab=governance",
    "steps": [
      "Review reminder configuration and delivery health in Governance & health.",
      "Confirm institutional approval and the actual scheduler configuration.",
      "Resolve delivery failures through the authorized operational process.",
      "Do not assume that a reminder was sent just because a task exists."
    ],
    "note": "The installed advanced release left reminders OFF and did not install a scheduler. Chat does not enable either."
  },
  "team": {
    "id": "team",
    "title": "Staff roles and permissions",
    "roles": [
      "head"
    ],
    "cloud": false,
    "words": [
      "team",
      "permission",
      "staff role",
      "access control",
      "teams",
      "permissions"
    ],
    "route": "guidance/index.php?view=team",
    "steps": [
      "Open the existing Guidance team controls.",
      "Review the least access needed for each responsibility.",
      "Use the existing protected forms and verification steps.",
      "Test the resulting account boundaries, including direct URLs and exports."
    ],
    "note": "The assistant cannot promote users, broaden assignments, disable security or grant itself permissions."
  },
  "followthrough": {
    "id": "followthrough",
    "title": "Follow-through tasks",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": false,
    "words": [
      "task",
      "follow-through",
      "follow through",
      "followup",
      "follow up",
      "desk",
      "tasks",
      "follow-throughs",
      "followups",
      "desks"
    ],
    "route": "guidance/staff.php?tab=desk",
    "steps": [
      "Open Follow-through in Staff workspace.",
      "Review tasks permitted for your role and current assignments.",
      "Use existing forms to record completion or approved reassignment.",
      "Keep supporting information in the authorized record rather than chat."
    ],
    "note": "Operations is limited to activity follow-through. Clinical tasks remain with authorized clinical staff; the assistant does not modify tasks."
  },
  "notifications": {
    "id": "notifications",
    "title": "Notifications",
    "roles": [
      "head",
      "counselor",
      "administrator"
    ],
    "cloud": false,
    "words": [
      "notification",
      "unread",
      "bell",
      "notifications",
      "unreads",
      "bells"
    ],
    "route": "guidance/index.php?view=notifications",
    "steps": [
      "Open the existing notification bell or Notifications page.",
      "Review only notifications available to your signed-in account.",
      "Open the linked authorized item before acting.",
      "Mark notifications read using the existing controls."
    ],
    "note": "The assistant does not read notifications, send messages or infer that a notification was delivered."
  },
  "services": {
    "id": "services",
    "title": "Guidance service categories",
    "roles": [
      "head"
    ],
    "cloud": false,
    "words": [
      "service category",
      "manage services",
      "service categories"
    ],
    "route": "guidance/index.php?view=categories",
    "steps": [
      "Open Guidance services with an authorized Head account.",
      "Review existing service categories before adding or editing one.",
      "Use the existing protected forms and check downstream routing.",
      "Coordinate shared-office changes with the relevant owner."
    ],
    "note": "Chat does not edit categories, routing or other offices."
  }
}
CATALOG, true, 32, JSON_THROW_ON_ERROR);
