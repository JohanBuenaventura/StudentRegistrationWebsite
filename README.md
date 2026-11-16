# Distributed Student Registration System

A decoupled, multi-tier CRUD (Create, Read, Update, Delete) web application built as an Operating Systems course project. This project demonstrates **service isolation**, **network bridging**, and **inter-process communication (IPC)** across a virtualized environment.

---

## 🏗️ Architecture & Network Topology

The system is split across two isolated environments communicating over a TCP/IP network bridge:
*   **Frontend Tier (Client/Web Server):** WSL Ubuntu running Apache2 and PHP 8.x, processing user input and rendering the UI.
*   **Backend Tier (Database Server):** VirtualBox Kali Linux VM running MariaDB, storing and securing relational student records.
*   **Communication:** Connected via VirtualBox **Bridged Networking**, allowing WSL to route database queries across a virtual network interface to Port `3306`.

---

## 🛠️ Tech Stack
*   **Web Server:** Apache2 (WSL Ubuntu)
*   **Application Language:** Native PHP (utilizing PHP Data Objects - PDO for secure prepared statements)
*   **Database:** MariaDB (Kali Linux VM)
*   **Styling:** Vanilla CSS

---

## 📂 Project Structure
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
└── README.md             # Project documentation
