# ProgrammersArena

ProgrammersArena is an online contest platform that supports input/output problems and ICPC-like contests. The platform includes features for checking solutions using Docker containers for PHP, Python, and C++, with ICPC problems specifically checked using C++. It also includes a Simplified Elo-based rating system for contests.

## Table of Contents

- [Installation](#installation)
- [Building the Project](#building-the-project)
- [Running the Project](#running-the-project)
- [Usage](#usage)
- [License](#license)

## Installation

### Prerequisites

- Docker: Ensure Docker is installed on your system. You can download Docker from [here](https://www.docker.com/products/docker-desktop).
- PHP: Required for Laravel.
- Node.js: Required for React and frontend build.

### Clone the Repository

Clone the project repository from GitHub:

```sh
git clone https://github.com/mali-ab/programmers-arena.git
cd programmers-arena
```

### Set up SSH keys for passwordless access

Setting up SSH keys for passwordless access involves generating a key pair on your local machine and configuring the remote host to trust your public key. Here’s how to do it:

#### 1. **Generate SSH Key Pair**

Run the following command on your local machine or the machine where the PHP script runs:

```bash
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"
```

- You’ll be prompted to specify a file location. Press `Enter` to accept the default (`~/.ssh/id_rsa`).
- If you want to add a passphrase, enter it. Otherwise, press `Enter` twice for no passphrase.

This generates two files:

- **Private key**: `~/.ssh/id_rsa` (Keep this secure, do not share it.)
- **Public key**: `~/.ssh/id_rsa.pub`

#### 2. **Copy the Public Key to the Remote Host**

Use the `ssh-copy-id` command to copy the public key to the remote host:

```bash
ssh-copy-id user@remote-host-ip
```

- Replace `user` with your username on the remote host and `remote-host-ip` with the host’s IP address.
- You will be prompted for the remote user’s password once. After this, the key will be copied to the remote server.

#### 3. **Verify Passwordless Access**

Try logging into the remote server:

```bash
ssh user@remote-host-ip
```

If configured correctly, you won’t be prompted for a password.

#### 4. **Manually Add the Key (If Needed)**

If `ssh-copy-id` is not available, you can manually add the key:

1. Copy the contents of the public key file:

   ```bash
   cat ~/.ssh/id_rsa.pub
   ```
2. On the remote host, open the `~/.ssh/authorized_keys` file:

   ```bash
   nano ~/.ssh/authorized_keys
   ```
3. Paste the public key into the file. Save and exit.
4. Set proper permissions on the `~/.ssh` directory and `authorized_keys` file:

   ```bash
   chmod 700 ~/.ssh
   chmod 600 ~/.ssh/authorized_keys
   ```

#### 5. **Configure SSH for PHP (Optional)**

If your PHP script will run under a web server user (like `www-data`), ensure the private key is accessible to that user. Alternatively, you can specify the key file explicitly in the SSH command:

```php
$sshCommand = "ssh -i /path/to/private_key user@remote-host-ip 'your-command'";
```

#### 6. **Secure Your Keys**

- Protect your private key with a passphrase for added security.
- Restrict access to the private key file:
  ```bash
  chmod 600 ~/.ssh/id_rsa
  ```

#### Troubleshooting

- **Key not working?** Ensure the `sshd` service on the remote host is running and the `authorized_keys` file is properly configured.
- **Permissions issue?** Ensure the `.ssh` folder and `authorized_keys` file on the remote host have the correct permissions.

Once set up, you’ll have passwordless access to the remote host, allowing your PHP script to execute SSH commands without prompting for a password.

### Build Docker Images

Build the Docker images in remote or local host for different languages. Location of Dockerfiles in dockerFiles directory.

#### Example:

```sh
cd dockerFiles/gcc && docker build -t gcc:10 -f Dockerfile.gcc-10 && cd ..
```

## Building the Project

### Run with Docker Compose

To build and start all services (Laravel backend, React frontend, and PostgreSQL database), run:

```sh
docker-compose up --build -d
```

To stop the containers later:

```sh
docker-compose down
```

### Laravel Backend

1. Install PHP Dependencies:

```sh
composer install
```

2. Set Up Environment:

Copy the .env.example file to .env and configure it as needed:

```sh
cp .env.example .env
```

3. Generate Application Key:

```sh
php artisan key:generate
```

4. Storage link:

```sh
php artisan storage:link
```

5. Run Migrations:

```sh
php artisan migrate
```

6. Create temp folder:

```sh
mkdir storage/app/temp
```

7. Verify the Storage Path Permissions:

```sh
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

### React Frontend

1. Navigate to the React Directory:

```sh
cd react
```

2. Install Node.js Dependencies:

```sh
npm install
```

3. Build the React Project:

```sh
npm run build
```

### Delete unverified users and queue work

Run the Scheduler and Jobs

```sh
* * * * * cd /path-to-your-laravel-app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path-to-your-laravel-app && php artisan queue:work >> /path-to-your-laravel-app 2>&1
```

## Running the Project

### Start Laravel Queue Worker

Run the Laravel queue worker to process jobs:

```sh
php artisan queue:work
```

### Start Laravel Development Server

If you are not using Docker for the backend, you can start the Laravel server with:

```sh
php artisan serve
```

### Start React Development Server

If you need to run the React development server:

```sh
cd react
npm run dev
```

## Usage

1. Access the Laravel backend at http://localhost:8000 (or the port specified by Docker).
2. Access the React frontend at http://localhost:3000 (or the port specified by Docker).

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Additional Notes

- **Docker Setup:** Ensure Docker is running before building or running the images.
- **Environment Configuration:** Update `.env` files with appropriate settings for database connections and other environment-specific configurations.
- **Queue Workers:** Configure your queue connections in `.env` and make sure the queue worker is running to handle background tasks.

Feel free to adjust paths, repository URLs, and configurations based on your project’s specifics.
