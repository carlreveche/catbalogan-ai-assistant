# AI-Based Virtual Assistant for the Municipality of Catbalogan

A PHP + MySQL web app that lets citizens create an account, chat with a
keyword-matching assistant about **Barangay Clearance**, **New Business
Permit**, and **Business Permit Renewal**, and view their past conversations.
It can run fully offline or optionally use Groq for grounded natural-language
answers.

---

## 1. Features

**Citizens**
- Register / log in (with password policy), or **sign in with Gmail
  (Google OAuth 2.0)**.
- Chat with the assistant: requirements, steps, fees, processing time,
  deadline, location — in English **and Tagalog** (e.g. "Magkano po ba ang
  business permit?").
- Follow-up questions — after asking about a permit, "what about the fees?"
  continues on the same topic.
- "Did you mean…" suggestions and clickable follow-up chips after each answer.
- Rate each assistant reply with 👍 / 👎 (toggle on/off).
- Conversation history with auto-generated titles, search, rename and delete.
- Typing indicator, timestamps, topic tags, mobile-friendly sidebar drawer.

**Administrators** (`admin/`)
- Dashboard: user/conversation/message counts, most-asked topics, helpfulness
  rate, recent activity.
- Manage **permits** and **knowledge base** entries without touching SQL.
- Review citizen answer feedback (helpful vs. unhelpful).

**Security**
- CSRF tokens on all forms and AJAX calls.
- Session ID regeneration on login/logout.
- `.htaccess` blocking direct access to `config/`, `includes/`, and database
  files.
- Prepared statements (PDO) throughout; all output escaped.
- API credentials are read from server environment variables, not source files.

---

## 2. How the "AI" works

The application always has an offline keyword/FAQ matcher. If a Groq API key
is configured, the application can use Groq as a grounded fallback; otherwise
it remains fully offline. The matcher pipeline is:

1. **Normalize** — lowercase, strip punctuation, remove Tagalog stop words
   ("po", "ba", "ang", "ng"…).
2. **Stem/fuzzy** — light suffix stemming (`requirements` → `requirement`)
   plus Levenshtein typo tolerance ("requirments" still matches).
3. **Topic** — which permit? Scored via word-overlap against stored keyword
   synonyms + unique signal words ("renew" → Renewal).
4. **Intent** — requirements / steps / fees / deadline / processing time /
   where, via signal words.
5. **Context** — if the topic is unclear but the citizen just asked about a
   permit, it follows up on that same permit.
6. **Fallback** — with "did you mean" style topic suggestions.

Every answer text lives in the `kb_entries` table, editable from the admin
panel — no PHP changes needed to extend the bot.

---

## 3. Setup Instructions (XAMPP)

### Configure the application

1. Copy the `catbalogan-ai-assistant` folder into `C:\xampp\htdocs`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Use `.env.example` as the variable reference. Set the `CATBALOGAN_*`
   variables in Apache/PHP or the hosting provider's environment settings; the
   application does not load `.env` files automatically.
4. Do not commit a local environment file or place secrets in PHP files.
5. Import the project's database schema and seed data into MySQL before
   opening the application. This source snapshot does not include an installer
   or SQL dump, so obtain/export those files separately before deployment.
6. Promote a user to admin after registration:
   `UPDATE users SET role = 'admin' WHERE email = 'you@example.com';`

The default local database values are `localhost`, database
`catbalogan_ai_assistant`, user `root`, and an empty password. Change them for
any shared or production server.

## 3b. Enable Gmail / Google Sign-In (optional)

1. Go to the [Google Cloud Console](https://console.cloud.google.com) and
   create a project.
2. **APIs & Services → OAuth consent screen** → configure the app
   (External, app name, support email).
3. **APIs & Services → Credentials → Create Credentials → OAuth Client ID**,
   type **Web application**.
4. Add the authorized redirect URI:
   `http://localhost/catbalogan-ai-assistant/google_auth/callback.php`
   (use HTTPS in production).
5. Set `CATBALOGAN_GOOGLE_CLIENT_ID`, `CATBALOGAN_GOOGLE_CLIENT_SECRET`, and
   `CATBALOGAN_GOOGLE_REDIRECT_URI` in the server environment.
6. The "Continue with Google" button appears on the login/register pages when
   both credentials are configured.

How it behaves: an existing account with the same email gets linked; a new
Gmail address automatically creates a citizen account (random unknown
password — password login can never hijack it). The OAuth `state` parameter
(CSRF) and the Google `id_token` (audience/expiry/issuer) are verified in
`google_auth/callback.php` before any login happens.

> Note: the OAuth flow needs internet access to reach Google's endpoints
> (cURL). Google sign-in also works on `http://localhost` without SSL.

---

## 4. Project Structure

```
catbalogan-ai-assistant/
├── config/
│   ├── db.php                 # MySQL connection (PDO)
│   ├── ai.php                 # Optional Groq settings
│   └── google_auth.php        # Optional Google OAuth settings
├── includes/
│   └── functions.php          # Auth + CSRF + Google OAuth + AI engine
├── google_auth/
│   ├── login.php              # Redirects to Google's consent screen
│   └── callback.php           # Verifies token, links/creates the account
├── api/
│   ├── ask.php                # Send message → answer, saves both to DB
│   ├── history.php            # Load a conversation's messages
│   ├── feedback.php           # Rate an assistant reply (👍/👎)
│   └── conversation.php       # Rename / delete conversations
├── admin/                     # Admin panel (requires role = 'admin')
│   ├── index.php              #   Dashboard / analytics
│   ├── reports.php            #   Reports (calendar date range, topic chart, gaps)
│   ├── export.php             #   CSV export for reports
│   ├── users.php              #   User management (roles, suspend, delete)
│   ├── permits.php            #   Manage permits
│   ├── kb.php                 #   Manage knowledge base entries
│   ├── feedback.php           #   Review answer ratings
│   └── includes/              #   Shared layout
├── assets/
│   ├── css/style.css
│   └── js/chat.js             # Chat UI (typing, chips, feedback, history)
├── index.php / login.php / register.php / logout.php / chat.php
└── README.md
```

## 5. Database Tables

- **users** — citizen + admin accounts (`role`: citizen | admin; `status`:
  active | suspended for admin-managed blocks; `google_id` + `avatar` for
  Gmail sign-in)
- **chats** — every message, grouped by `conversation_id` (`title` = auto name,
  `matched_topic` = permit code matched)
- **permits** — one row per permit/clearance type
- **kb_entries** — the matcher's "brain": keywords → canned answers per intent
- **chat_feedback** — one 👍/👎 rating per assistant message

## 6. Extending the assistant

Add a new permit (e.g. "Cedula"):
1. In the **Admin → Permits** page, create it (a starter overview answer is
   auto-generated so the matcher can detect it immediately).
2. In **Admin → Knowledge Base**, add `requirements`, `steps`, `fees`, etc.
   entries for it. Add Tagalog keyword synonyms for better matching.
3. Optionally add a unique trigger word in `TOPIC_SIGNAL_WORDS`
   (`includes/functions.php`) if it needs one to disambiguate.

## 7. Notes & Limitations

- Rule-based matching remains the offline fallback; very specific phrasings
  may still miss.
- When Groq is enabled, user messages, recent conversation history, and
  official knowledge-base content are sent to Groq. Do not enable it until
  your privacy notice and data-handling requirements have been reviewed.
- Seed data is sample/reference content; verify against Catbalogan City's BPLO
  and barangay offices before production use.
- No SMS/email notifications or online permit filing — by design, per the
  manuscript's stated scope. This system provides information and guidance.
