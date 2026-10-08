# Evaluation Consolidation Issue: Duplicate Tables During HR Manager Review

## Current Evaluation Approval Flow

The system follows this organizational approval sequence:

1. Team Self-Ratings
2. Supervisor Consolidation
3. Manager Review
4. Division VP (if applicable)
5. President & CEO
6. Audit Committee
7. Board of Directors

**Special Rule:** Departments reporting directly to the President, such as Human Resources, Marketing, and Business Development, skip the Division VP stage and proceed directly from Manager Review to President & CEO.

## Identified Issue

I encountered an issue with the evaluation consolidation process involving three employees from the Human Resources Department:

- Elena Delgado
- Patricia Gomez
- Miguel Torres

### Scenario 1: Elena Delgado and Patricia Gomez

Elena Delgado and Patricia Gomez were the first employees to complete their self-rating evaluations.

After submitting their evaluations, both were forwarded to the HR Supervisor for consolidation.

The HR Supervisor reviewed and approved their evaluations, allowing them to proceed to **Step 3: HR Manager Review**.

At this point, the system correctly placed Elena Delgado and Patricia Gomez together in a single consolidation table under HR Manager Review.

### Scenario 2: Miguel Torres

Miguel Torres completed his self-rating evaluation later than Elena Delgado and Patricia Gomez.

While Elena and Patricia had already advanced to HR Manager Review, Miguel's evaluation was still pending at **Step 2: HR Supervisor Consolidation**.

Later, the HR Supervisor reviewed and approved Miguel Torres's evaluation.

After approval, Miguel's evaluation successfully advanced to **Step 3: HR Manager Review**.

However, instead of placing Miguel Torres in the existing consolidation table alongside Elena Delgado and Patricia Gomez, the system created a completely separate table for his evaluation.

## Current Incorrect Behavior

The HR Manager Review stage now displays two separate consolidation tables:

**Table 1 — HR Manager Review**

| Employee | Evaluation Stage |
|---|---|
| Elena Delgado | Manager Review |
| Patricia Gomez | Manager Review |

**Table 2 — HR Manager Review**

| Employee | Evaluation Stage |
|---|---|
| Miguel Torres | Manager Review |

This is incorrect because all three employees belong to the same department, participate in the same evaluation cycle, and have reached the same approval stage.

The system appears to be creating separate consolidation tables based on when employees advance to the next stage rather than grouping them according to their evaluation cycle and organizational approval stage.

## Expected Correct Behavior

When the HR Supervisor approves Miguel Torres's evaluation, the system should automatically add Miguel to the **existing HR Manager Review consolidation table** that already contains Elena Delgado and Patricia Gomez.

**Expected Table — HR Manager Review**

| Employee | Evaluation Stage |
|---|---|
| Elena Delgado | Manager Review |
| Patricia Gomez | Manager Review |
| Miguel Torres | Manager Review |

There should be only one consolidated table for employees belonging to the same department, evaluation cycle, and approval stage.

The system must support employees completing their evaluations at different times without generating unnecessary additional tables.

## Required Fix

Please investigate and correct the evaluation consolidation logic.

**The system should follow these rules:**

1. When an employee's evaluation is approved and forwarded to the next stage, check whether an existing consolidation table is already available for the same department, evaluation cycle, and approval stage.
2. If an existing table is found, automatically include the employee in that table instead of generating another table.
3. Create a new consolidation group only when no matching group exists.
4. Ensure that late submissions and approvals do not cause duplicate consolidation tables.
5. Maintain each employee's individual evaluation records, approval status, and history.
6. Prevent duplicate employee entries within the same consolidation group.
7. Apply the same consolidation behavior consistently throughout the entire organizational approval workflow.
8. Preserve the existing approval routing rules, including the Division VP bypass for departments reporting directly to the President.

## Important Consideration

**Evaluation consolidation should be based on organizational grouping, not submission timing.**

For example, even if Elena Delgado and Patricia Gomez complete their evaluations on Monday and Miguel Torres completes his on Wednesday, all three should appear in the same HR Manager Review consolidation table once their evaluations have been approved by the HR Supervisor.

The system must dynamically update the existing consolidation table as additional employees become eligible for the same approval stage.

**Final Objective:** Fix the duplicate consolidation table issue without disrupting the existing evaluation workflow, individual approval tracking, or previously consolidated employee evaluations.


note for codex:
the user will send the actuall screenshot of the spotted anomaly in the system please don't start coding yet to avoid misunderstanding.
make sure the evaluation phasing after changes has been applied not only to hr Supervisor and hr manager but to all stage/phase/Organizational Approval Route.