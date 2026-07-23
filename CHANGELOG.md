# Changelog

All notable changes to this plugin are documented in this file.

## [0.28.16] - 2026-07-23

### Added

- Added run-level default driver selection to route schedules, allowing a specific run to use its own regular driver instead of the route's default driver.

## [0.28.15] - 2026-07-22

### Fixed

- Show explicit multi-day vacancy runs in the dispatcher assignment table even when the route does not normally run on that weekday.

## [0.28.14] - 2026-07-22

### Added

- Added a rich morning dispatch summary email for vacant and substitute-covered runs.

## [0.28.13] - 2026-07-22

### Fixed

- Format affected driver names as First Last in admin/dispatcher route schedule-change notifications.

## [0.28.12] - 2026-07-22

### Changed

- Updated driver-facing route schedule-change notifications to use "You were" wording.

## [0.28.11] - 2026-07-22

### Changed

- Include the affected driver's name in admin/dispatcher route schedule-change notifications.

## [0.28.10] - 2026-07-22

### Changed

- Removed the legacy `terricel_dispatch` role from operations schedule-change recipient lookup.

## [0.28.9] - 2026-07-22

### Changed

- Made route schedule-change notifications describe assigned/unassigned driver changes with route, run, and date details.
- Grouped operations schedule-change notifications so bulk school/district changes notify the initial higher-level event instead of each affected route.

## [0.28.8] - 2026-07-22

### Fixed

- Include route-management capability users in operations schedule-change notifications so dispatcher accounts are notified regardless of role slug.

## [0.28.7] - 2026-07-22

### Fixed

- Include the legacy Terricel Dispatch role in operations schedule-change notifications.

## [0.28.6] - 2026-07-22

### Changed

- Updated driver schedule-change notifications to say whether the driver was assigned or unassigned, including route, run, date, and only explicit reasons.

## [0.28.5] - 2026-07-22

### Added

- Notify Terricel admins and dispatchers of all route schedule changes according to their profile notification settings.

## [0.28.1] - 2026-07-22

### Fixed

- Added an opt-in “Add Any Driver” override to the Dispatch Route List so dispatchers can assign a substitute driver from the full active driver list, including drivers already assigned to another route for the same day, to support approved double-up coverage.

## [0.28.0] - 2026-07-19

### Changed

- Promoted the module from the parent development folder into the standalone Terricel Transit Dispatch child plugin.
- Updated the plugin display name and documentation to clarify that the parent owns the module tree while WordPress owns activation.

## [0.27.0] - 2026-05-04

### Added

- Added a route edit warning that highlights scheduled route runs when the selected default driver is not available for the matching day and run.
- Updated the driver regular availability editor to use seven weekday columns on desktop and one day per row on tablet and mobile displays.

## [0.26.0] - 2026-05-02

### Fixed

- Updated Dispatch Dashboard vacancy detection so scheduled routes with no default driver are shown as vacant unless a substitute is assigned.

## [0.25.0] - 2026-05-01

### Changed

- Updated Dispatch Dashboard coverage so routes without scheduled runs for a date are not treated as vacant.

## [0.24.0] - 2026-05-01

### Changed

- Updated Weekly Dispatch Dashboard collapsed day counts to count uncovered route runs instead of route rows.

## [0.23.0] - 2026-05-01

### Changed

- Updated Weekly Dispatch Dashboard day sections to be collapsed by default with vacant route counts in each header.

## [0.22.0] - 2026-05-01

### Added

- Added Daily and Weekly admin dispatch dashboard views, with Weekly showing today plus the next seven days.

## [0.21.0] - 2026-04-29

### Changed

- Updated the Regular Drivers Out dashboard section to include active open or covered vacancies for the day.

## [0.20.0] - 2026-04-29

### Changed

- Updated the Dispatch Dashboard route list to show non-collapsible scheduled run rows under each route with per-run substitute driver selections and start times.

## [0.19.0] - 2026-04-29

### Changed

- Updated the Dispatch Dashboard substitute dropdown to include float drivers with all-day regular availability and exclude drivers already assigned as substitutes for the day.

## [0.18.0] - 2026-04-29

### Changed

- Added an Available all day checkbox to each driver regular availability day card.

## [0.17.0] - 2026-04-29

### Changed

- Updated vacancy entry to select the driver first, populate the driver's default route, allow route overrides, and select scheduled runs across the vacancy date range.

## [0.16.0] - 2026-04-29

### Changed

- Renamed the Module 1 parent settings tab to Dispatch.
- Changed Standard Run Names from a textarea to stable editable run-name records used by route schedules and driver schedule buttons.

## [0.15.0] - 2026-04-29

### Changed

- Moved Standard Run Names settings into Module 1 so the parent settings panel only appears while Module 1 is active.

## [0.14.0] - 2026-04-29

### Changed

- Updated route schedule run names to use the parent plugin Standard Run Names setting.

## [0.13.0] - 2026-04-29

### Changed

- Added an Add Run button to the route schedule editor that saves and refreshes the route record.

## [0.12.0] - 2026-04-29

### Changed

- Updated route schedule runs to use Edit and confirmed Remove row actions.

## [0.11.0] - 2026-04-29

### Added

- Added regular route schedules to parent bus route records with multi-day run entry support.

## [0.10.0] - 2026-04-29

### Changed

- Changed route vacancies to be driven by the regular driver who is out.
- Removed manual vacancy status and priority fields from the vacancy form.
- Derived vacancy status from whether an assigned substitute driver is selected.

## [0.9.0] - 2026-04-29

### Added

- Added regular weekly driver availability controls to parent driver profiles.

## [0.8.0] - 2026-04-29

### Changed

- Updated route vacancies to support start and end dates for extended substitute coverage.
- Active vacancy assignments now feed the Dispatch Dashboard route list for dates within the vacancy range.

## [0.7.0] - 2026-04-29

### Added

- Added today-only substitute driver assignment dropdowns to the Dispatch Dashboard route list.

## [0.6.0] - 2026-04-27

### Changed

- Limited the dispatcher route list to vacant routes and routes with substitute drivers.
- Updated dispatcher route sorting so route numbers use natural order.

## [0.5.0] - 2026-04-27

### Changed

- Renamed the module menu/page label to Dispatch Dashboard.
- Updated multi-school routes to display once with all linked schools listed together.

## [0.4.0] - 2026-04-27

### Changed

- Updated the dispatcher dashboard to show parent-owned routes by district and school.
- Prioritized unassigned routes in red, followed by routes covered by substitute drivers.
- Added regular-driver-out tracking with out-through dates and duration display.

## [0.3.0] - 2026-04-25

### Added

- Added structured Daily Route Schedule records for route status tracking.
- Added structured Driver Availability records with substitute driver flags.
- Added structured Route Vacancy records with status and priority tracking.
- Added a Route Coverage dashboard for daily route status, availability, substitute bench, open vacancies, and notification settings.
- Added dispatcher/admin email alert scaffolding for unassigned, at-risk, high-priority, and urgent coverage issues.

## [0.2.0] - 2026-04-25

### Changed

- Updated Route Coverage to use parent-owned bus route records.
- Added route vacancy scaffold for Module 1 workflows.
- Updated the module dashboard copy around route management, vacancies, and dispatcher/admin notifications.

## [0.1.0] - 2026-04-25

### Added

- Created the Route Coverage child module framework.
- Added parent dependency handling and admin notice.
- Registered Route Coverage with the Terricel Transit parent module registry.
- Added route and coverage record post type scaffolds.
- Added initial module admin page and Smart Monitor integration.
