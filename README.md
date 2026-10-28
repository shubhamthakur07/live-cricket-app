# 🏏 Live Cricket Hub & CricHeroes Social App

A modern full-stack cricket web application featuring **live ball-by-ball Cricbuzz match tracking**, **CricHeroes-style match & squad management**, **WhatsApp player lookup**, and an **Instagram-style cricket community feed**.

Built with **Laravel 11**, **MySQL / phpMyAdmin**, **Tailwind CSS**, **Alpine.js**, and a dedicated **Python FastAPI live scoring microservice**.

---

## 🌟 Key Features

### 1. ⚡ Live Scores & Cricbuzz Engine
- Real-time live scorecards, match summaries, and ball-by-ball commentary.
- Top match story bubbles for active international & domestic matches.
- Automated match updates via local high-speed Python scraper microservice.

### 2. 📱 CricHeroes-Style Match & Squad Management
- **Start / Edit Matches:** Create tournament, series, overs, leather/tennis ball types, and match venues.
- **WhatsApp Player Lookup:** Enter player phone numbers to auto-fetch career statistics and link playing profiles.
- **Playing XI Squad Builder:** Add batsmen, bowlers, designate Captain (C) and Wicketkeeper (WK).
- **Match Center:** Categorized by Live, Scheduled, Completed, and Abandoned.

### 3. 📸 Instagram-Style Cricket Feed & Community
- Post match updates, memes, news, and match discussions.
- Heart likes, comment threads, and instant post sharing.
- Follow / Unfollow cricket fans and players.

### 4. 👤 Cricket Career Passport (User Profile)
- Detailed player career cards: Matches played, Total runs, Strike rate, Highest score, Batting average, Wickets taken, Best bowling figures, and Economy rate.
- Bio, phone number, and customizable profile avatars.

---

## 🏗️ Architecture

```text
live-cricket-app/
├── app/                  # Laravel 11 Backend (Controllers, Models, Services)
├── resources/views/      # Blade Frontend UI (Tailwind CSS, Alpine.js)
├── routes/               # Web & API routes
├── database/             # Migrations & database schemas (MySQL / SQLite)
└── score-engine/         # Python FastAPI Live Scoring Microservice (Port 6020)
    ├── app.py            # Cricbuzz scraper & real-time REST API
    ├── requirements.txt  # Python dependencies
    └── run.py            # Microservice runner
```

---

## 🚀 Getting Started

### Prerequisites
- PHP >= 8.2 & Composer
- Node.js >= 18 & npm
- Python >= 3.10
- MySQL Server / MariaDB (or SQLite)

---

### Step 1: Backend & Frontend Setup (Laravel)

```bash
# 1. Clone repository
git clone https://github.com/<your-username>/live-cricket-app.git
cd live-cricket-app

# 2. Install PHP dependencies
composer install

# 3. Setup environment file
cp .env.example .env
php artisan key:generate

# 4. Configure your database in .env and run migrations
php artisan migrate

# 5. Install frontend assets and build
npm install
npm run build

# 6. Start Laravel server
php artisan serve --port=8001
```

---

### Step 2: Live Score Engine Setup (Python Microservice)

```bash
cd score-engine

# 1. Create and activate virtual environment
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# 2. Install Python dependencies
pip install -r requirements.txt

# 3. Start Python Live Score Service on Port 6020
uvicorn app:app --host 127.0.0.1 --port 6020 --reload
```

Now open **`http://localhost:8001`** in your browser! 🏏

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
