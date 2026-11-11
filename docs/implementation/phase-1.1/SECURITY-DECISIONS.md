# Phase 1.1 security decisions

| Area | Decision | Reason / compatibility |
| --- | --- | --- |
| Audit visibility | Super Admin and HR: system-wide; Department Supervisor: own department | Explicit user clarification. Scope applies to rows and aggregates. |
| Applicant decisions | HR and Super Admin accept/reject; supervisors perform placement lifecycle work in their department | Matches HR and supervisor dashboard actions and existing individual endpoint restrictions. |
| Bulk updates | Entire batch succeeds or none does | Prevents partial review when any ID is invalid, inaccessible, or has an invalid transition. |
| Public vacancies | Approved and unexpired only; public field allowlist | Prevents disclosure of internal approval/creator fields and unpublished entries. |
| Password setting | At least 12 characters, no arbitrary composition requirement, PHP default hash | Consistent policy without breaking current users; existing hashes upgrade after successful login. |
| Vacancy expiration | Explicit scheduled CLI task | Keeps GET read-only and makes the write operation deployer-controlled. |

The README's older statement that supervisors perform applicant decisions was corrected to match implemented authorization. These decisions do not establish production readiness.
