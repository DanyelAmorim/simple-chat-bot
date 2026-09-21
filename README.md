# Simple Chat Bot

SENTRY AI is a local web chatbot that runs an AI model through `llama.cpp`.
The application uses PHP and SQLite and does not depend on an external AI API.

## Features

- User registration and login
- Password hashing with PHP's password API
- Local AI chat through `llama-server`
- Conversation history stored per user
- New conversation creation
- Conversation deletion
- Logout and session protection
- CSRF protection for form submissions
- Recent conversation context sent to the model

## Technologies

- PHP
- HTML5
- CSS3
- SQLite
- `llama.cpp`
- A local `.gguf` language model

The PHP application communicates with the local `llama-server` through its
OpenAI-compatible HTTP endpoint on `127.0.0.1:8082`. No external AI service is
required.

## Requirements

- Linux or another operating system supported by `llama.cpp`
- PHP with the following extensions:
	- `pdo_sqlite`
	- `sqlite3`
	- `curl`
- SQLite command-line tool (optional, useful for inspecting the database)
- A compiled `llama-server` binary
- A compatible `.gguf` model file
- Enough RAM for the selected model and context size
- Permission to execute the model server and write to the session/database files

Check the PHP extensions with:

```bash
php -m | grep -E 'PDO|pdo_sqlite|sqlite3|curl'
```

## Project Structure

```text
.
├── chat.php
├── historic.php
├── index.php
├── database/
│   └── database.sqlite
├── engine/
│   └── engine.php
├── llamaSH/
│   └── llama.sh
├── model/
└── style/
		└── style.css
```

## Configuration

Before running the project, update the absolute paths in `llamaSH/llama.sh`:

```bash
LLAMA_DIR="/path/to/llama.cpp"
MODEL_PATH="/path/to/project/model/your-model.gguf"
```

The current script is configured for a specific machine and currently uses
`/home/tlk/Documents/AIX/llama.cpp` and `/var/www/html/model/...`. These paths
must be changed when the project is installed in another location.

The model server listens on `127.0.0.1:8082`, while the PHP development server
can use port `8001`.

## Running the Project

### 1. Start the local AI server

From the project root:

```bash
chmod +x llamaSH/llama.sh
bash llamaSH/llama.sh
```

### 2. Start the PHP server

Use a writable session directory if the default PHP session directory does not
allow the current user to write session files:

```bash
mkdir -p /tmp/php-sessions
chmod 700 /tmp/php-sessions
php -d session.save_path=/tmp/php-sessions -S localhost:8001
```

Open the application at:

```text
http://localhost:8001
```

## Database

The SQLite database is stored at `database/database.sqlite`. The application
creates the required tables automatically:

- `users`
- `chats`
- `messages`

To inspect the database:

```bash
sqlite3 database/database.sqlite
```

Inside SQLite:

```sql
.tables
SELECT * FROM users;
SELECT * FROM chats;
SELECT * FROM messages;
.quit
```

## Git Workflow

Create a separate branch before making changes:

```bash
git switch -c feature-name
```

After editing files:

```bash
git status
git add .
git commit -m "Describe the change"
git push -u origin feature-name
```

Then open a pull request from the feature branch into `main`.

## Limitations

- AI responses are limited by the model's context window and available RAM.
- The application currently sends only the most recent messages to the model.
- The AI server and the PHP server must both be running.
- The model file is not included in the repository.
- The development server is not intended for production deployment.

