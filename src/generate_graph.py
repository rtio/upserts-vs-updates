import json
import matplotlib.pyplot as plt

with open("results/results.json") as f:
    data = json.load(f)

sizes = [r["batch_size"] for r in data]
update = [r["update"] for r in data]
upsert = [r["upsert"] for r in data]

plt.plot(sizes, update, marker="o", label="Bulk UPDATE")
plt.plot(sizes, upsert, marker="o", label="UPSERT (Insert on Dup Key)")

plt.xlabel("Batch Size")
plt.ylabel("Time (seconds)")
plt.title("Bulk UPDATE vs UPSERT Performance")
plt.legend()
plt.grid(True)

# Corrected path below:
plt.savefig("results/benchmark.png")
print("Graph generated at results/benchmark.png")
