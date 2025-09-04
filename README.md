# Project Overview

This project powers the Jeroen website admin and API.

## Logging

All errors encountered while saving JSON data are written to `/data/logs/app.log` using PHP's `error_log`.
Ensure the `/data/logs` directory is writable on deployment to allow monitoring and troubleshooting.
