# Pig_Team_API

This is an API that is used to transfer local data from a SQL DB and info on the computer to the UNL Server.

## Prerequisites

Before you begin, ensure you have met the following requirements:
- **PHP**: PHP 7.4 or higher installed on your machine.
- **Composer**: Composer is required for managing PHP dependencies.

## Installation

Follow these steps to install the project on your local machine:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/your-project.git
   cd your-project
2. **Install PHP dependencies**
    run: composer install
3. **Set up environmental file**
    DB_CONNECTION=mysq  
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=your_database_name
    DB_USERNAME=your_database_username
    DB_PASSWORD=your_database_password
4. **Run Database Migration**
    run: php artisan migrate
5. **Run / test application**
    run: php artisan serve (or) php artisan test 
