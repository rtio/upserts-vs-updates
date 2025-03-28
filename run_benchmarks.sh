#!/bin/bash

echo "Starting containers..."
docker compose up -d
sleep 10 # Wait for MySQL to initialize

echo "Generating initial data..."
docker compose exec php php generate_data.php

echo "Running benchmarks..."
docker compose exec php php benchmark.php

echo "Generating results graph..."
docker compose exec php bash -c "
  apt-get update && apt-get install -y python3 python3-pip python3-venv &&
  python3 -m venv graph-env &&
  ./graph-env/bin/pip install matplotlib &&
  ./graph-env/bin/python generate_graph.py
"

echo "Benchmark completed! Results available in results/benchmark.png"

echo "Stopping containers..."
docker compose down
