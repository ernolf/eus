# eus
## ernolfs utility scripts - little script flakes for linux admins

---
---

#### **networking/fbwanip**
  - grab WAN IP from your fritzbox.

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

#### **time-conversion/d2u**
  - convert human readable date format to unixtime (seconds since the unix epoch = 1970-01-01 00:00 UTC)
#### **time-conversion/u2d**
  - convert unixtime to human readable date format
#### **time-conversion/unix2datelong**
  - convert unixtime to human readable long date format
#### **time-conversion/filetime2unix**
  - convert windows filetime (a 64-bit value representing the number of 100-nanosecond intervals since 1601-01-01- 00:00 UTC) to unixtime and human readable time format
#### **time-conversion/unix2filetime**
  - convert unixtime or human readable time to windows filetime
#### **time-conversion/today**
  - little calendar of actual month, today is highlighted
#### **time-conversion/year**
  - little calendar of actual year

---

