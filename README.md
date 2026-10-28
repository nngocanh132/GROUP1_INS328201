<<<<<<< Updated upstream
# group1_CapstoneProjectII
=======
# group1_ins328201
# Vinamilk Contract and Document Management System

## 1. Project Overview

The Vinamilk Contract and Document Management System is a web-based application designed to support contract and document management within Vinamilk. The system aims to organize contract information, manage related documents, and support collaboration between internal staff, managers, and external users.

## 2. Technologies Used

- **HTML** – Structures web pages.
- **CSS** – Styles the user interface.
- **JavaScript** – Handles client-side interactions and form validation.
- **PHP** – Processes server-side logic and database operations.
- **MySQL** – Stores and manages application data.
- **Apache** – Serves the application locally through XAMPP.
- **Git and GitHub** – Support version control and team collaboration.
- **Visual Studio Code (VS Code)** – Used for development.

## 3. Prerequisites

Install the following tools before setting up the project:

| Tool | Purpose | Official Website |
|---|---|---|
| XAMPP | Provides Apache, PHP, and MySQL | https://www.apachefriends.org/ |
| Git | Version control and collaboration | https://git-scm.com/downloads |
| Visual Studio Code | Code editor | https://code.visualstudio.com/ |
| Web browser | Access and test the application | Google Chrome or another modern browser |

## 4. Installation and Setup

### Step 1: Install the Required Tools

Install XAMPP, Git, and Visual Studio Code on your computer.

Open the XAMPP Control Panel and ensure that Apache and MySQL are available.

### Step 2: Clone the Repository

Open Git Bash or the VS Code terminal and run:

```bash
git clone https://github.com/nngocanh132/GROUP1_INS328201.git
cd GROUP1_INS328201
```

If the repository has already been cloned, skip this step and open the existing project folder.

### Step 3: Configure the Project Directory

The repository contains the project folder:

vinamilk_contract_and_document_management_system

For local development with XAMPP, place the project folder inside the htdocs directory.

The expected project path is:

C:\xampp\htdocs\vinamilk_contract_and_document_management_system

If the repository is cloned directly into C:\xampp\htdocs, the project folder will be nested inside GROUP1_INS328201. In that case, move the inner project folder into htdocs or adjust the setup instructions to match the actual directory structure.

Start Apache and MySQL from the XAMPP Control Panel before running the application.

### Step 4: Start XAMPP

1. Open the XAMPP Control Panel.
2. Start **Apache**.
3. Start **MySQL**.

Make sure both services are running before proceeding.

### Step 5: Set Up the Database

1. Open `http://localhost/phpmyadmin` in your browser.
2. Create a MySQL database for the project.
3. Import the project's SQL file if one is provided.
4. Configure the PHP database connection using the actual database name, username, password, and host.

The database name and connection settings must match the project's implementation. If the project does not yet include a database SQL file, migration scripts, or seed data, these must be created before the database setup can be reproduced by other team members.

### Step 6: Run the Application

Open the following URL in your browser:

```text
http://localhost/vinamilk_contract_and_document_management_system/
```

Apache will serve the project from the `htdocs` directory. Ensure that the project contains a valid entry point, such as `index.php` or `index.html`.

## 5. Environment Configuration

If the project uses environment variables, create a local `.env` file based on `.env.example`.

Example `.env.example`:

```dotenv
APP_NAME="Vinamilk Contract and Document Management System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/vinamilk_contract_and_document_management_system/

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=root
DB_PASSWORD=
```

**Important:**
- The values above are examples and must match the local development environment.
- PHP must be configured to load these variables before the application can use them.
- Do not commit `.env` or real database credentials to GitHub.
- Add `.env` to `.gitignore`.
- Keep `.env.example` free of real passwords and other secrets.

If the application currently uses database credentials directly in its PHP configuration, follow the existing implementation until environment-variable loading is configured.

## 6. Git Workflow

The team uses Git and GitHub to manage source code and track individual contributions.

1. Each member works on their assigned branch.
2. Commit changes with clear, meaningful commit messages.
3. Push the branch to GitHub.
4. Create a Pull Request to merge changes into `main`.
5. Review and test changes before merging.
6. Keep the `main` branch runnable and up to date.

Do not commit passwords, private credentials, temporary files, or unnecessary generated files.

## 7. Setup Verification

Each team member should verify the following:

- [ ] XAMPP, Git, and VS Code are installed.
- [ ] The repository can be cloned successfully.
- [ ] The project is located in the correct `htdocs` directory.
- [ ] Apache starts successfully.
- [ ] MySQL starts successfully.
- [ ] The application opens in a web browser.
- [ ] The PHP application can connect to MySQL when database integration is implemented.
- [ ] Database setup instructions have been tested.
- [ ] Any setup errors and their solutions have been documented.

## 8. Troubleshooting

**Apache fails to start:** Check whether another application is using the configured Apache ports.

**MySQL fails to start:** Check the XAMPP logs and verify that the MySQL port is not occupied by another service.

**The application cannot be found:** Verify the folder name and confirm that the project is inside `C:\xampp\htdocs`.

**Database connection fails:** Check the database name, host, port, username, password, and PHP database connection settings.

**Changes do not appear:** Refresh the browser and verify that you are editing the project in the correct directory.

## 9. Project Status

This README documents the intended local development setup. Database scripts, environment-variable loading, and application features should be marked as complete only after they have been implemented and tested.
>>>>>>> Stashed changes
