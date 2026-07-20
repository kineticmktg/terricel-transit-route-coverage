# Terricel Transit Dispatch

Child module plugin for Terricel Transit Operations.

Publisher: Kinetic Marketing LLC  
URL: https://kineticmktg.com

## Purpose

This plugin registers the Dispatch Dashboard module with the Terricel Transit Operations parent plugin. When active, it appears under the **Terricel Transit** admin menu and the parent's **Terricel Transit > Modules** page.

The framework includes:

- Parent plugin dependency notice.
- Module registration with the parent registry.
- Daily Route Schedule records for daily route status tracking.
- Driver Availability records for availability and substitute coverage tracking.
- Route Vacancy records for open coverage needs, status, and priority.
- Dispatch Dashboard page under the parent menu.
- Dispatcher/admin email alert scaffolding for unassigned or at-risk routes.
- Smart Monitor integration through shared Terricel events.

The parent plugin owns districts, schools, bus routes, drivers, and bus numbers. This module uses that shared data to add route management workflows, vacancy tracking, and dispatcher/admin notification behavior.

## Module 1 Workflow

- Daily route schedules link to parent-owned Bus Routes and active Drivers.
- Driver availability records link to parent-owned Drivers and can mark a driver as available for substitute coverage.
- Driver availability records can track when a regular driver is out and how long they are expected to be out.
- Route vacancy records link to parent-owned Bus Routes and can be marked open, covered, or cancelled.
- The dispatcher dashboard lists only vacant routes and substitute-covered routes using parent-owned district and school data, with unassigned routes shown first in red and substitute-covered routes shown next.
- High-priority and urgent open vacancies, plus unassigned or at-risk daily schedules, create Smart Monitor events and queue email notifications when alert recipients are configured.

## Parent Requirement

The Terricel Transit Operations parent plugin must be installed and active:

- `terricel-logistics-plugin`

## Module Hook

The child plugin registers during:

```php
terricel_logistics_register_modules
```

The registered module extends the parent `Terricel_Logistics_Module` base class.
