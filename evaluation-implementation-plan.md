# Evaluation System Implementation Plan

This plan extends the existing evaluation flow rather than creating a separate module. It covers form creation and visibility, individual review routing, and shared behavior scoring.

## Implementation Status

The first implementation pass is in place. Reporting review routes and template targeting are added, and shared behavior scores now use department + period + framework version. Existing templates without target-position or framework metadata retain their legacy behavior until deliberately mapped.

## Decisions Captured

- The President/CEO can create forms across departments, but only for manager-level positions. Template creation and management are exposed through the Employee Portal, not the HRIS navigation.
- Department managers and supervisors can create forms only for configured direct subordinate positions in their authorized department. Staff cannot create forms by default.
- Template ownership is based on `evaluation_templates.created_by`: only the original creator can edit, archive, restore, notify from, or delete a template. President/CEO, HR Manager, HR Supervisor, and HR Staff accounts can view templates created by other authorized roles but cannot mutate them.
- Target positions are assigned automatically from the selected department and the creator's configured scope: President/CEO gets every active rank-3 manager position there; HR Manager/HR Supervisor get every active position directly reporting to their active position. A template can target multiple positions, and employee self-rating eligibility and package member populations use that full target set. OJT, Trainee, Probationary, and Project Based employees remain eligible only for Initial/Final evaluations; they are excluded from Annual/Quarterly availability, submission, notifications, and package populations.
- Forms can have different KRAs by position. Equivalent behavior questions use a separate Behavior Framework Code; Form Code continues to identify an individual form.
- Evaluation routing applies globally across all departments, not only HR. On submission, the employee's self-rating goes to their immediate active supervisor/manager; after that reviewer completes their review, the evaluation advances to the next reviewer above them, continuing one level at a time through the organizational chain.
- Employee-specific `employees.reports_to` takes precedence when it points to an active employee. Otherwise, use the configured position hierarchy in `job_titles.reports_to`.
- If multiple active employees are eligible to review at a step, prompt all eligible reviewers and let the first to claim handle it.
- For position-based routing, use every active account holder of the exact configured parent position. If it is vacant, use active positions in the same department and rank, preferring Position I; if none is available, hold for reassignment.
- If there is no active reviewer for a configured step, hold the evaluation for authorized reassignment rather than skipping that step.
- After individual reviews finish, evaluations continue through existing package consolidation and governance. The President/CEO's individual review and package-level sign-off remain separate.
- Shared behavior scoring is an organizational rule: calculate it department-wide per evaluation period across applicable forms with the same Behavior Framework Code and Version. Only submitted evaluations with complete behavior ratings contribute; open package scores are provisional and finalized packages retain their frozen score. Keep each employee's KRA individual and position-specific.

For example, the chain can be **HR Staff → HR Supervisor → HR Manager → President/CEO**. The same rule applies in every department using its configured reporting assignments and position hierarchy; for another department, the actual supervisor, manager, and executive positions determine the path.

## Phases

### 1. Confirm the Eligible-Reviewer Rule — Done

Active `employees.reports_to` assignments identify one reviewer when that employee and account are active. Otherwise, the configured `job_titles.reports_to` position supplies the candidate group. All active account holders of that exact position may claim; only when it is vacant does the fallback search active positions in the same department and rank, preferring Position I.

### 2. Add Position-Targeted Templates and Creator Authorization — Done

The template wizard stores target department, target position, and Behavior Framework Code/Version.

- President/CEO can select manager-level positions across departments; managers and supervisors are limited to configured direct subordinate positions in their department.
- Server-side checks protect create, edit, list, archive, and delete operations. HR Staff cannot create templates.
- The checked-in schema and runtime migration support the President/CEO role and new targeting fields.

### 3. Make Evaluation Visibility and Membership Position-Aware — Done

Self-rating discovery and submission checks enforce department and position. Package population is position-aware.

- Direct template IDs are validated server-side. Package summaries count only active employees in the target position.
- Existing templates with null position targets remain available under the former department-wide rule.

### 4. Add Individual Reporting-Chain Review Steps — Done

Implemented per-evaluation route records separately from package-level review steps. Self-ratings advance through active reporting assignments, with atomic claims, endorsement/return actions, assignment refresh, and HR reassignment for vacancies.

- Routed evaluations use a distinct `Pending Reporting Review` status so the legacy supervisor-endorsement queue cannot claim or process the same evaluation; the runtime migration also moves existing in-progress routed evaluations out of the legacy queue.
- Apply this flow to employees in every department. A submitted self-rating first goes to the employee's immediate active reviewer, not directly to package consolidation.
- Resolve each next reviewer using the agreed precedence: active individual assignment first, configured position hierarchy as fallback.
- When multiple active employees are eligible for the reviewer step, notify/prompt them and let the first eligible claimant atomically claim the evaluation.
- Require the assigned claimant to review/endorse before routing the evaluation to the next reviewer in that employee's reporting chain.
- Continue level by level until the configured reporting chain is complete (for example: staff member → supervisor → manager → department head or President/CEO, as configured).
- If a configured step has no active eligible holder, keep the evaluation pending and surface it for authorized reassignment; do not silently skip to a higher level.
- After the individual chain completes, hand the evaluation into the existing package consolidation and governance workflow without treating those package decisions as a substitute for individual review.

### 5. Add Shared Behavior Framework Identity and Calculation — Done

Templates now store a versioned Behavior Framework Code and linked active forms must define matching behavior names and KPI descriptions.

- Preserve separate KRAs and form identities.
- Ensure linked forms use the same behavior question identities, wording, scale, and scoring rules.
- Calculate the shared score by department, evaluation period, and behavior framework—not by template.
- Average eligible participants' individual behavior scores; do not include KRA scores in that average.
- Continue to show individual behavior and shared behavior as distinct values.
- Incomplete evaluations do not contribute. The score refreshes across open packages and approved/finalized package scores are not recalculated.

Legacy templates without framework metadata continue to calculate shared behavior within their own template cohort.

### 6. Test and Migrate — In Progress

Focused local MariaDB checks verified active reviewer resolution, reporting-step handoff, creator scopes, framework compatibility, and a 3.00 shared score across two position-specific forms. PHP lint and `git diff --check` pass.

- Verify creator permissions and reject unauthorized department/position submissions.
- Verify correct template visibility and package membership by position.
- Complete browser testing of global reporting-chain routing, including simultaneous claims, HR reassignment UI, and governance sign-off.
- Confirm review order, handoff into package consolidation, and separate President/CEO package sign-off.
- Test shared scores across position-specific forms in the same department/framework/period, and verify different departments remain separate.
- Check framework version changes, incomplete submissions, transfers, late submissions, and score finalization.
- Migrate existing forms deliberately: preserve current form codes and map existing behavior criteria only where they actually match the shared framework.

## Recommended Delivery Order

Implement in this order:

1. Reviewer eligibility rule.
2. Schema and authorization.
3. Position-aware template visibility and membership.
4. Individual review routing.
5. Shared behavior framework and scoring.
6. Migration and end-to-end tests.

This sequence establishes the data and permissions before routing depends on them, and it prevents a template from becoming visible or score-eligible to the wrong employees during rollout.
