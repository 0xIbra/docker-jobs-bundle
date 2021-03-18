Monitoring Dashboard
====================

To manage and monitor jobs, there is a dashboard interface that you can activate.



### Preview
<div style="display: flex;">
  <div style="margin-right: 20px">
    <h4>Job detail</h4>
    <img src="assets/docker-jobs-job-detail.jpg" width="400" />
  </div>

  <div>
    <h4>Dashboard</h4>
    <img src="assets/docker-jobs-dashboard.png" width="400" />
  </div>
</div>


Configuration
-------------
All you have to do is to import the bundle annotation routes:
```yaml
# routes.yaml - Symfony 4
# routing.yml - Symfony 2 - 3


polkovnik_docker_jobs:
  resource: '@DockerJobsBundle/Controller'
  type: annotation
# prefix: /admin ?

```

**Important note:**  
For the moment, there is no Authentication protecting these routes.  
If you want to protect them, you must do it yourself in `security.yaml` under `access_control:`.
