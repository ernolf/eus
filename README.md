# eus
## ernolfs utility scripts - little script flakes for linux admins

---

#### **system-monitoring/cpumon**
  - monitors CPU usage and log processes that consume all available CPU resources to identify potential bottlenecks and performance issues.
    - Start it as a daemon with a high priority, so that it keeps logging, even if all other processes are frozen (as user root):
      ```
      nohup nice -n -20 "system-monitoring/cpumon" &>/dev/null &
      ```
    - Stop (as user root):
      ```
      kill $(pgrep -f "cpumon");fg
      ```
---

