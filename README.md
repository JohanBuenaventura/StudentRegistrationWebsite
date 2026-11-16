# Distributed Student Registration System

A decoupled, multi-tier CRUD (Create, Read, Update, Delete) web application built as an Operating Systems course project. This project demonstrates **service isolation**, **network bridging**, and **inter-process communication (IPC)** across a virtualized environment.

---

## 🏗️ Architecture & Network Topology

The system is split across two isolated environments communicating over a TCP/IP network bridge:
* **Frontend Tier (Web Server):** WSL Ubuntu running Apache2 and PHP 8.x, processing user input and rendering the UI.
* **Backend Tier (Database Server):** VirtualBox Kali Linux VM running MariaDB, storing and securing relational student records.
* **Communication:** Connected via VirtualBox **Bridged Networking**, allowing WSL to route database queries across a virtual network interface to Port `3306`.

---

## 🛠️ Tech Stack & Project Structure

* **Web Server:** Apache2 (WSL Ubuntu)
* **Application Language:** Native PHP (utilizing PHP Data Objects - PDO for secure prepared statements)
* **Database:** MariaDB (Kali Linux VM)
* **Styling:** Vanilla CSS

```text
/var/www/html/
│
├── db_config.php         # Centralized PDO database connection handler
├── index.php             # Student registration form (Create)
├── view_students.php     # Dashboard listing records with Edit/Delete actions (Read)
├── edit_student.php      # Student update interface and logic (Update)
├── delete_student.php    # Record removal handler (Delete)
├── style.css             # UI styling
├── .gitignore            # Git exclusion rules
└── README.md             # Project documentation & setup guide
```

---

## 🗄️ Initial Database Schema Setup (One-Time)

If setting up the Kali Linux VM from scratch, install MariaDB (`sudo apt install mariadb-server`), set `bind-address = 0.0.0.0` in `/etc/mysql/mariadb.conf.d/50-server.cnf`, and execute the following SQL commands inside `sudo mysql`:

```sql
CREATE DATABASE student_registration;

CREATE USER 'dbadmin'@'%' IDENTIFIED BY 'password123';
GRANT ALL PRIVILEGES ON student_registration.* TO 'dbadmin'@'%';
FLUSH PRIVILEGES;

USE student_registration;

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    course VARCHAR(100) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🚀 Step-by-Step Launch & Execution Guide

Follow these steps in exact order to launch your development environment, connect the WSL web server to the VirtualBox database, and run the application.

### Step 1: Open CMD and Launch WSL
1. Press the **Windows Key**, type `cmd`, and press **Enter** to open the Windows Command Prompt.
2. Enter your Ubuntu WSL environment by running:
   ```cmd
   wsl
   ```
3. Navigate directly to your Apache web root directory:
   ```bash
   cd /var/www/html
   ```
4. *(Optional)* Open the project folder in VS Code:
   ```bash
   code .
   ```

### Step 2: Start and Verify the Database Server (VirtualBox Kali VM)
1. Open **Oracle VM VirtualBox** and start your **Kali Linux VM**.
2. Ensure the VM's Network Adapter is set to **Bridged Adapter** (matching your active network interface).
3. Open the Kali terminal and find the VM's current IP address:
   ```bash
   ip a
   ```
   *(Note the `inet` IP address, e.g., `172.28.193.204`).*
4. Restart the MariaDB service to bind it to the active network:
   ```bash
   sudo systemctl restart mariadb
   ```

### Step 3: Connect WSL Frontend to the VM Database
1. Back in your **WSL terminal**, verify network reachability by pinging the Kali VM IP:
   ```bash
   ping -c 3 172.28.193.204
   ```
2. Open `db_config.php` and ensure `$host` matches your Kali VM's current IP address:
   ```bash
   nano /var/www/html/db_config.php
   ```
   ```php
   <?php
   $host = '172.28.193.204'; // Update if VM IP changes
   $dbname = 'student_registration';
   $username = 'dbadmin';
   $password = 'password123';
   ```

### Step 4: Start Apache and Launch the Web App
1. Start the Apache web server in your WSL terminal (if it is not already set to auto-start):
   ```bash
   sudo systemctl start apache2
   ```
2. Open your Windows web browser and navigate to:
   * **Registration Page:** `http://localhost/index.php`
   * **Student Records Dashboard:** `http://localhost/view_students.php`

---

## 🔧 Troubleshooting Quick Reference

* **`No route to host` error:** Your VM's IP address changed after switching networks. Re-run `ip a` in Kali, update `$host` in `db_config.php`, and run `sudo systemctl restart mariadb`.
* **Default Ubuntu Apache page loads:** Remove the default HTML file using `sudo rm /var/www/html/index.html`.
* **Git permission denied:** Reclaim folder ownership using `sudo chown -R $USER:$USER /var/www/html`.