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

#### Warning:  
In the `--command` option, you must provide the full command to run by the job.  

**Example:**  
If your job is to run a symfony command let's assume `my:command:run`, the full command would be:  
`bin/console my:command:run` If your `container_working_dir` is set to your project.  
If not, `/path/to/symfony_project/bin/console my:command:run`.

Command: `polkovnik:jobs:submit`

    Description:
      Creates a new job and submits it to a queue for processing.

    Usage:
      polkovnik:jobs:submit [options]
      polkovnik:jobs:submit --command "run:my:command --arg1=yes"

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
      polkovnik:jobs:stop --job-id 330

    Options:
      -j, --job-id=JOB-ID   Job ID



## Clean orphan jobs command
What are orphan jobs ?  
Well, they are jobs that have "running" state but have no container that is executing them.
These jobs can be provoked by manually stopping/deleting the container, system reload, or if ever, Docker engine crashes.

Command: `polkovnik:jobs:clean`

    Description:
      Removes orphan jobs from the running state.

    Usage:
      polkovnik:jobs:clean [options]
      polkovnik:jobs:clean --queue notifications
      polkovnik:jobs:clean --dry-run

    Options:
          --queue[=QUEUE]      Queue from which will be removed orphan jobs.
          --dry-run[=DRY-RUN]  Run without applying changes. [default: false]
