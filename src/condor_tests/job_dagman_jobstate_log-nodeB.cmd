executable   = ./job_dagman_jobstate_log-nodeB.pl
arguments    = $(nodename)
universe     = scheduler
output       = $(nodename).out
error        = $(nodename).err
log          = job_dagman_jobstate_log-nodeB.log
Notification = NEVER
+pegasus_site = "viz"
queue 2
