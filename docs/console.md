Console Commands
----------------
There are a few commands to perform various actions.

* [Job orchestration command](#job-orchestration-command)
* [Job submission command](#job-submission-command)
* [Stop job command](#stop-job-command)

## Job orchestration command
This command is the core of this bundle, it is this command that will run and handle all your jobs.  

Command: `polkovnik:jobs:orchestrate`  

    Description:
    Orchestrates Docker containers and handles them in their different stages.

    Usage:
    polkovnik:jobs:orchestrate --queue="2nd-queue" --concurrency=6 --update-logs-eager=false

    Options:
      --queue[=QUEUE]                          Queue to process.
      --update-logs-eager[=UPDATE-LOGS-EAGER]  When enabled, updates the logs of running jobs every few seconds, otherwise only updates at the end of the job. [default: true]
      --concurrency[=CONCURRENCY]              Specify how many jobs you want to run concurrently. [default: 4]


## Job submission command
This command let's you submit a new job.

Command: `polkovnik:jobs:submit`

    Description:
      Creates a new job and submits it to a queue for processing.

    Usage:
      polkovnik:jobs:submit [options]
      polkovnik:jobs:submit "run:my:command --arg1=yes"

    Options:
          --command=COMMAND                    command to execute.
          --queue[=QUEUE]                      queue where to submit the job. [default: "default"]
          --docker-image-id[=DOCKER-IMAGE-ID]  ID of the docker image that must be used to execute the job.


## Stop job command
This command let's you stop a running job.

Command: `polkovnik:jobs:stop`

    Description:
      Terminates a running job.

    Usage:
      polkovnik:jobs:stop [options]

    Options:
      -j, --job-id=JOB-ID   Job ID
