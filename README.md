# MySQL to Kintone Data Migration Tool

This tool synchronizes data between a MySQL database and Kintone. It supports both creating new records and updating existing ones based on unique identifiers.

## Features

- Connects to a MySQL database and retrieves data.
- Sends data to Kintone, either creating new records or updating existing ones.
- Logs operations to the console for monitoring.

## Important Notes

- Changes made in Kintone will **not** be reflected back to MySQL.
- If records are deleted from MySQL, they will **remain** in Kintone.

## Requirements

- PHP 7.0 or higher (Tested with PHP 8.0.30)
- MariaDB (Tested with MariaDB 10.6.21)
- Composer (for dependency management)
- Access to a MySQL database
- Kintone account with API access

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/yourusername/mysql-to-kintone.git
   cd mysql-to-kintone
   ```

2. Install dependencies (if any):

   ```bash
   composer install
   ```

3. Create a `.env` file based on the `.env.example` template:

   ```bash
   cp .env.example .env
   ```

4. Update the `.env` file with your database and Kintone credentials:

   ```env
   # Database Connection
   MYSQL_SERVERNAME=your_mysql_server
   MYSQL_USERNAME=your_mysql_username
   MYSQL_PASSWORD=your_mysql_password
   MYSQL_DBNAME=your_database_name

   # Kintone Configuration
   KINTONE_DOMAIN=your_kintone_domain
   ```

## Usage

Run the script from the command line:

```bash
php mysql-to-kintone.php
```

The script will connect to the MySQL database, retrieve data based on the queries defined in the `.env` file, and send it to Kintone.

## Logging

All operations are logged to the console. Ensure that you monitor the output for any errors or success messages.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Kintone API Documentation](https://developer.kintone.io/hc/en-us)
- [MySQL Documentation](https://dev.mysql.com/doc/)

---
Made with ❤️ for the Kintone community 