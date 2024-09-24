# Dashboard - A meaningful life with tasks

## Description
Dashboard for personal use. Task management and stickynotes, my way of trying out HTMX.

Version

1.0.0

## Installation
### Prerequisites

PHP (version 8.1 or higher)

Apache web server with mod_rewrite enabled

Npm

### Steps

**Clone the repository:**

    - Copygit clone https://github.com/MajorJohnDoe/dashboard.git

    - cd dashboard

**Install npm dependencies:**

    - npm install

**Configure your web server:**

    - Ensure that the document root is set to the project's public directory.

    - Make sure the .htaccess file is present in the root directory and that Apache is configured to allow .htaccess overrides.

**Set up your PHP environment:**

    - Copy config.example.php to config.php and update the configuration settings as needed.

(Optional) If using a database, set up your database and update the connection details in config.php. Both MariaDB and MySQL should work.

## Usage
Access the dashboard through your web browser by navigating to the configured domain or localhost address. You should be directed to a login page where you can login with username: johndoe and password: password.

Features

- Task management
- Sticky notes
- Create sticky notes with voice-to-text transcription for easier note-taking. The app requires an OpenAI API key for speech recognition.
- TinyMCE integration for rich text editing
- Sortable elements using SortableJS

## Dependencies

TinyMCE (^7.0.1)

SortableJS (1.15.2)

## Screenshots

![View over a task management board](screenshot_1.jpg)

![Edit task with label search functionality](screenshot_2.jpg)

![Task history. Go back and look at older finished/archived tasks](screenshot_3.jpg)

![Stickynotes overview](screenshot_4.jpg)

![Stickynotes, edit sticky note](screenshot_5.jpg)