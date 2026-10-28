# Live Cricket Score Engine (Microservice)

High-performance real-time Python FastAPI microservice that fetches and structures live cricket scores, ball-by-ball updates, commentary, and match schedules from Cricbuzz.

Used by the main **Live Cricket Laravel Application** to power live scorecards, match stories, and CricHeroes integrations.

---

## 🚀 Quick Setup & Run

### 1. Create and Activate Virtual Environment
```bash
python3 -m venv venv
source venv/bin/activate
```

### 2. Install Dependencies
```bash
pip install -r requirements.txt
```

### 3. Run the Service
```bash
# Runs on Port 6020 (required by Laravel CricketDataService)
uvicorn app:app --host 127.0.0.1 --port 6020 --reload
```
Or simply:
```bash
python run.py
```

---

## 📡 API Endpoints
- `GET /matches` - Live, scheduled, and completed cricket matches
- `GET /matches/{id}` - Detailed scorecard for a specific match
- `GET /cricbuzz/{id}` - Ball-by-ball commentary and live over analysis
- `GET /docs` - Interactive Swagger API documentation
