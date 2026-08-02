# API Reference — Chat & Announcements

Route, controller, and access-control reference for the real-time communication module. See [README.md](../README.md#real-time-communication) for feature description and behavior.

## Chat Endpoints

| Method | Route | Controller | Auth |
|---|---|---|---|
| GET | `/chat` | `ChatController@index` | Authenticated |
| GET | `/chat/start?user=&ta=` | `ChatController@start` | Authenticated |
| GET | `/chat/{conversation}` | `ChatController@show` | Participant |
| POST | `/chat/{conversation}` | `ChatController@store` | Participant |
| POST | `/chat/{conversation}/attach-options` | `ChatController@attachOptions` | Participant |
| PUT | `/chat/{conversation}/{message}` | `ChatController@update` | Message sender |

**Purpose:**

- `index` — List conversations with unread message counts.
- `start` — Find or create a conversation for the given user/thesis pair; redirect to the thread.
- `show` — View a conversation; auto-marks it as read.
- `store` — Send a message with an optional file attachment.
- `attachOptions` — Return a JSON list of attachable Workspace files / Logbook entries.
- `update` — Edit a message (only within the 15-minute edit window).

## Announcement Endpoints

| Method | Route | Controller | Auth |
|---|---|---|---|
| GET | `/announcements` | `AnnouncementController@index` | Authenticated |
| GET | `/announcements/create` | `AnnouncementController@create` | Supervisor/Admin |
| POST | `/announcements` | `AnnouncementController@store` | Supervisor/Admin |
| GET | `/announcements/{id}/report` | `AnnouncementController@report` | Sender/Admin |
| POST | `/announcements/{id}/read` | `AnnouncementController@markRead` | Recipient |
| POST | `/announcements/{id}/remind` | `AnnouncementController@remindUnread` | Sender/Admin |

**Purpose:**

- `index` — Senders see sent announcements + read counts; students see received announcements.
- `create` — Announcement composition form.
- `store` — Create an announcement, fan out to recipients, and send notifications.
- `report` — Read/unread recipient breakdown.
- `markRead` — Mark an announcement as read by the current recipient.
- `remindUnread` — Re-send notifications to recipients who haven't read it yet.

## Access Control

See `ChatController@authorizeChat`:

- **Admins** — Chat with any user (scoped to institution in `institution` mode).
- **Supervisors** — Chat with supervised/examined students (`pembimbing_1/2` or `penguji_1/2`) or admins. Two supervisors cannot directly message each other.
- **Students** — Chat with assigned supervisors/examiners or admins. Students cannot message other students.
- **File attachments** — Re-authorized per thesis: only admins, the file owner (student), or assigned supervisors/examiners can attach. Receiver permissions follow existing Workspace/logbook policies.

## Database Schema

See migration `2026_08_02_120000_create_chat_tables.php`:

```sql
conversations
  id, mahasiswa_ta_id (nullable FK->mahasiswa_ta),
  user_one_id, user_two_id (FK->users, cascade),
  created_at, updated_at
  UNIQUE (mahasiswa_ta_id, user_one_id, user_two_id)

messages
  id, conversation_id (FK cascade),
  sender_id (FK->users cascade),
  body (text),
  attachable_type, attachable_id    -- Polymorphic: WorkspaceFile | LogbookEntry
  read_at, edited_at, created_at, updated_at

announcements
  id, sender_id (FK->users cascade),
  institution_id (nullable FK->institutions, nullOnDelete),
  title, body, target_filter (nullable JSON),
  created_at, updated_at

announcement_recipients
  id, announcement_id (FK cascade), user_id (FK->users cascade),
  read_at (nullable), created_at, updated_at
  UNIQUE (announcement_id, user_id)
```
