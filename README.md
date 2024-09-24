# Dashboard - A meaningful life with tasks

## Description
Dashboard for personal use. Task management and stickynotes, my way of trying out HTMX.
Version
1.0.0

## Installation
### Prerequisites

PHP (version 8.1 or higher) <!-- Replace X.X with the minimum PHP version required -->
Apache web server with mod_rewrite enabled
Npm

### Steps

1. Clone the repository:
Copygit clone https://github.com/MajorJohnDoe/dashboard.git
cd dashboard

2. Install npm dependencies:
Copynpm install

3. Configure your web server:

Ensure that the document root is set to the project's public directory.
Make sure the .htaccess file is present in the root directory and that Apache is configured to allow .htaccess overrides.

4. Set up your PHP environment:

Copy config.example.php to config.php and update the configuration settings as needed.

5. (Optional) If using a database, set up your database and update the connection details in config.php. Both MariaDB and MySQL should work.

## Usage
Access the dashboard through your web browser by navigating to the configured domain or localhost address.
Features

- Task management
- Sticky notes
- Create notes with voice to text transcribing for easier notes, requires a Open AI API Key
- TinyMCE integration for rich text editing
- Sortable elements using SortableJS

## Dependencies

TinyMCE (^7.0.1)
SortableJS (1.15.2)

## Contributing
Instructions for how to contribute to your project (if applicable).
## License
Specify your project's license here.
## Support
Instructions for how users can get support or contact you regarding the project.