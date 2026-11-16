# Step-by-Step Setup & Launch Guide

Follow these steps in exact order to launch your development environment, connect the WSL web server to the VirtualBox database, and run the Student Registration system.

---

## Step 1: Open CMD and Launch WSL

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

---

## Step 2: Start and Verify the Database Server (VirtualBox Kali VM)

1. Open **Oracle VM VirtualBox** and start your **Kali Linux VM**.
2. Ensure the VM's Network Adapter is set to **Bridged Adapter** (matching your active network interface).
3. Open the Kali terminal and find the VM's current IP address:
   ```bash
   ip a
   ```
   *(Note the `inet` IP address, e.g., `172.28.193.204`).*
4. Ensure MariaDB is configured to listen on all network interfaces (`/etc/mysql/mariadb.conf.d/50-server.cnf` has `bind-address = 0.0.0.0`), then restart the database service:
   ```bash
   sudo systemctl restart mariadb
   ```

---

## Step 3: Connect WSL Frontend to the VM Database

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
   ...
   ```

---

## Step 4: Start Apache and Launch the Web App

1. Start the Apache web server in your WSL terminal (if it is not already running automatically):
   ```bash
   sudo systemctl start apache2
   ```
2. Open your Windows web browser and go to:
   * **Registration Page:** `http://localhost/index.php`
   * **Student Records Dashboard:** `http://localhost/view_students.php`

---

## Troubleshooting Quick Reference

* **No route to host:** Your VM's IP address changed after switching networks. Re-run `ip a` in Kali, update `$host` in `db_config.php`, and run `sudo systemctl restart mariadb`.
* **Default Ubuntu Apache page loads:** Remove the default HTML file using `sudo rm /var/www/html/index.html`.
* **Git permission denied:** Reclaim folder ownership using `sudo chown -R $USER:$USER /var/www/html`.