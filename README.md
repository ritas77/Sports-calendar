A sports calendar with standings and options to filter or edit events.

Assumptions and decisions:

1.Database-Driven Standings: Decided to handle league standings through Database Views. This ensures that complex ranking logic stays close to the data and remains consistent across the app.
2. Structured Filtering: Implemented a UI-based filtering system to help users quickly isolate specific leagues or dates within the master calendar.
3. Manual Data Management: Assumed that standings and event details are managed via manual user updates rather than external API synchronization.
4. This project is containerized using Docker, making it easy to set up and run in any environment.

* **Docker** and **Docker Compose** must be installed on your system.

Setup instructions:
1. **Download** the `host` folder.
2. **Open a terminal (or Powershell on Windows)** in that directory.
3. **Build and start** the containers by running:
   ```bash
   docker-compose up -d --build
4. Open your web browser and go to http://localhost:8080
If something is already running at that port:
- edit the `nginx.conf` file in `host` folder by editing line `Listen 8080` to `listen new_port`
- in `docker-compose.yml` change the line
  ports:
      - "8080:8080"
  to
  ports:
      - "new_port:new_port"
