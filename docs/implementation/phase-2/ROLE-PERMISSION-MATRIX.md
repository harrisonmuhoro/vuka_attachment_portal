# Phase 2 role-permission matrix

| Capability | Super Admin | HR Coordinator | Department Supervisor | Student |
|---|---|---|---|---|
| Applications read | All departments | All departments | Own department | Own records |
| Accept/reject applications | Yes, workflow validated | Yes, workflow validated | No | No |
| Placement lifecycle | All departments | All departments | Own department | No |
| Bulk review | Yes, scoped | Yes, scoped | Placement transitions only in own department | No |
| Audit records | All | All | Own department | No |
| Analytics | System-wide | System-wide | Own department | Own application summary |
| Vacancy approval | Yes | Yes | Request/own department operations | Public approved/open reads |
| Interview scheduling | All departments | All departments | Own department | Own interview response |
| Evaluations | All departments | All departments | Own department | Own permitted result |
| Administrator create/activate/deactivate | Yes, excluding self deactivation | No | No | No |
| Profile update | Own profile fields only | Own profile fields only | Own profile fields only | Own profile fields only |

Every permission is enforced server-side. Browser role flags and IDs are informational only. Out-of-scope resource reads return the same not-found/forbidden behavior already established by the application workflow.
