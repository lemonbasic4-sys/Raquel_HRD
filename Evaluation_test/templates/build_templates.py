"""Generate the standalone mock evaluation-template SQL from the fixture positions."""

from pathlib import Path


# These are test KRAs. A production form still needs the position owner's approved KRAs.
DEPARTMENTS = {
    "Acquired Properties": (1, {100, 101, 105, 108}, 108, [
        "Property inventory accuracy", "Acquisition case completeness", "Title document tracking",
        "Property valuation review", "Disposition milestone delivery", "Portfolio risk escalation",
        "Approved pricing support", "Property inspection follow-up", "Stakeholder coordination",
        "Portfolio report timeliness", "Acquired asset safeguarding", "Case handover quality"]),
    "Audit": (2, {200, 201, 203, 206}, 206, [
        "Audit plan coverage", "Working paper completeness", "Evidence quality",
        "Finding accuracy", "Control testing", "Risk prioritization",
        "Report delivery", "Corrective action follow-up", "Independence safeguards",
        "Branch audit coordination", "Issue escalation", "Audit file closure"]),
    "Business Development": (3, {300, 302, 303}, 302, [
        "Prospect research", "Qualified lead generation", "Partnership outreach",
        "Opportunity assessment", "Proposal preparation", "Market intelligence",
        "Pipeline record accuracy", "Stakeholder follow-up", "Growth initiative delivery",
        "Commercial risk escalation", "Conversion reporting", "Handover to operations"]),
    "Compliance": (4, {400, 401, 403, 404}, 403, [
        "Regulatory requirement tracking", "Policy review", "Compliance testing",
        "Incident documentation", "Exception escalation", "Remediation follow-up",
        "Training support", "Control evidence quality", "Deadline monitoring",
        "Branch advisory response", "Regulatory report preparation", "Compliance register accuracy"]),
    "Finance": (5, {500, 501, 502, 506, 511, 516}, 511, [
        "Transaction accuracy", "Reconciliation completion", "Financial record integrity",
        "Budget monitoring", "Variance explanation", "Cash control",
        "Report timeliness", "Approval documentation", "Tax and policy compliance",
        "Exception resolution", "Forecast support", "Period close readiness"]),
    "General Services": (6, {600, 601, 605, 609, 621}, 621, [
        "Facility request response", "Asset inventory accuracy", "Maintenance follow-up",
        "Supplier coordination", "Logistics scheduling", "Safety checks",
        "Service ticket closure", "Resource use", "Document control",
        "Issue escalation", "Delivery confirmation", "Operations reporting"]),
    "Human Resources": (7, {700, 705, 710, 711}, 710, [
        "Employee record accuracy", "Recruitment coordination", "Onboarding completion",
        "Personnel document control", "Employee inquiry response", "Policy communication",
        "Training coordination", "Performance process support", "Confidentiality compliance",
        "Deadline tracking", "Case escalation", "HR report timeliness"]),
    "Information Technology": (8, {800, 801, 804, 809, 810}, 809, [
        "Service availability", "Incident response", "Ticket documentation",
        "Change control", "Security patch follow-up", "Backup verification",
        "User support quality", "System monitoring", "Project milestone delivery",
        "Technical documentation", "Defect resolution", "Access control review"]),
    "Marketing": (9, {900, 901, 902, 903, 904}, 904, [
        "Campaign planning", "Brand guideline compliance", "Content quality",
        "Publication schedule delivery", "Audience research", "Channel performance tracking",
        "Creative asset review", "Lead handover", "Event coordination",
        "Campaign budget tracking", "Stakeholder approval", "Results reporting"]),
    "Office of the President": (10, {1101, 1102, 1103}, 1101, [
        "Executive calendar accuracy", "Meeting preparation", "Correspondence handling",
        "Decision record control", "Confidential file security", "Action item follow-up",
        "Visitor coordination", "Travel arrangement accuracy", "Executive brief preparation",
        "Document turnaround", "Stakeholder communication", "Office request resolution"]),
    "Operations": (11, {1000, 1001, 1004, 1007, 1012}, 1012, [
        "Branch service delivery", "Transaction accuracy", "Daily cash control",
        "Customer issue response", "Operational policy compliance", "Staff coordination",
        "Area visit follow-up", "Inventory safeguarding", "Exception escalation",
        "Target monitoring", "Branch report timeliness", "Corrective action completion"]),
    "Purchasing": (12, {1200, 1202}, 1202, [
        "Purchase request completeness", "Supplier sourcing", "Quotation comparison",
        "Approval documentation", "Purchase order accuracy", "Delivery follow-up",
        "Supplier performance review", "Cost control", "Contract record accuracy",
        "Procurement policy compliance", "Issue escalation", "Purchasing report timeliness"]),
}

BEHAVIORS = [
    ("Positive Attitude", "Displays positive attitude at work."),
    ("Respect", "Shows respect to all people in the organization."),
    ("Accountability", "Takes full responsibility of the job including special task or assignment."),
    ("Commitment", "Demonstrates strong commitment to the job."),
    ("Teamwork", "Works cooperatively with others in achieving the goals."),
    ("Integrity", "Exhibits honesty and strong moral uprightness."),
    ("Continuous Improvement", "Provides diligent effort to continuously focus on getting better."),
    ("Excellent Client Experience", "Delivers the service beyond the expectations of the internal and external clients."),
]


def quote(value: str) -> str:
    return "'" + value.replace("'", "''") + "'"


def build() -> str:
    lines = [
        "-- Mock templates for one freshly imported Evaluation_test department fixture.",
        "-- Run after that department's 01_*_test_people.sql, once per fresh test database.",
        "-- Only positions with an eligible employee and their active immediate-head account receive forms.",
        "USE raquel_hris;",
        "",
        "CREATE TEMPORARY TABLE fixture_template_positions (",
        "  department_id INT NOT NULL, job_title_id INT NOT NULL, probationary_position TINYINT(1) NOT NULL,",
        "  PRIMARY KEY (job_title_id)",
        ");",
        "INSERT INTO fixture_template_positions (department_id,job_title_id,probationary_position) VALUES",
    ]
    position_rows = []
    kra_rows = []
    for department, (department_id, positions, probationary_position, kra_names) in DEPARTMENTS.items():
        assert len(kra_names) == 12 and len(set(kra_names)) == 12
        for job_title_id in sorted(positions):
            position_rows.append(f"  ({department_id},{job_title_id},{int(job_title_id == probationary_position)})")
        for order, name in enumerate(kra_names, 1):
            weight = "8.34" if order <= 4 else "8.33"
            description = f"Complete assigned {name.lower()} work accurately and on time."
            kra_rows.append(f"  ({department_id},{order},{quote(name)},{quote(description)},{weight})")
    lines.extend([",\n".join(position_rows) + ";", "", "CREATE TEMPORARY TABLE fixture_template_kras (",
                  "  department_id INT NOT NULL, sort_order INT NOT NULL, name VARCHAR(100) NOT NULL,",
                  "  description VARCHAR(255) NOT NULL, weight DECIMAL(5,2) NOT NULL,",
                  "  PRIMARY KEY (department_id,sort_order)", ");",
                  "INSERT INTO fixture_template_kras (department_id,sort_order,name,description,weight) VALUES",
                  ",\n".join(kra_rows) + ";", "",
                  "CREATE TEMPORARY TABLE fixture_template_behaviors (",
                  "  sort_order INT NOT NULL PRIMARY KEY, name VARCHAR(100) NOT NULL, kpi VARCHAR(255) NOT NULL",
                  ");", "INSERT INTO fixture_template_behaviors (sort_order,name,kpi) VALUES"])
    lines.append(",\n".join(f"  ({index},{quote(name)},{quote(kpi)})" for index, (name, kpi) in enumerate(BEHAVIORS, 1)) + ";")
    lines.extend(["", "CREATE TEMPORARY TABLE fixture_template_types (type_no INT PRIMARY KEY, type_name VARCHAR(12));",
                  "INSERT INTO fixture_template_types VALUES (1,'Initial'),(2,'Final'),(3,'Quarterly'),(4,'Annual');",
                  "", "-- Build forms only for the department represented by the currently imported employees.",
                  "CREATE TEMPORARY TABLE fixture_ready_targets AS",
                  "SELECT p.department_id,p.job_title_id,p.probationary_position,MIN(u.user_id) AS creator_user_id",
                  "FROM fixture_template_positions p",
                  "JOIN job_titles j ON j.job_title_id=p.job_title_id AND j.department_id=p.department_id",
                  "JOIN employees e ON e.job_title_id=p.job_title_id AND e.department_id=p.department_id",
                  "  AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status IN ('Regular','Probationary')",
                  "JOIN employees h ON h.employee_id=e.reports_to AND h.job_title_id=j.reports_to",
                  "  AND h.is_active=1 AND h.deleted_at IS NULL AND h.employment_status='Regular'",
                  "JOIN users u ON u.employee_id=h.employee_id AND u.role IN ('Employee','President and CEO')",
                  "  AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0",
                  "GROUP BY p.department_id,p.job_title_id,p.probationary_position;",
                  "", "INSERT INTO evaluation_templates",
                  "  (template_id,template_name,description,target_department,target_department_id,",
                  "   target_job_title_id,behavior_framework_code,behavior_framework_version,",
                  "   evaluation_type,kra_weight,behavior_weight,form_code,status,created_by)",
                  "SELECT 200000+p.job_title_id*10+t.type_no,",
                  "  CONCAT('[TEST] ',j.job_title,' ',t.type_name),",
                  "  CONCAT('Mock ',t.type_name,' form for ',j.job_title,'. Replace KRAs with approved job requirements before production use.'),",
                  "  d.department_name,p.department_id,p.job_title_id,",
                  "  CASE WHEN t.type_no IN (3,4) THEN CONCAT(UPPER(t.type_name),'-CORE') ELSE NULL END,",
                  "  CASE WHEN t.type_no IN (3,4) THEN '1' ELSE NULL END,",
                  "  t.type_name,80.00,20.00,CONCAT('TEST-',p.department_id,'-',p.job_title_id,'-',t.type_no),",
                  "  'Active',p.creator_user_id",
                  "FROM fixture_ready_targets p",
                  "JOIN job_titles j ON j.job_title_id=p.job_title_id",
                  "JOIN departments d ON d.department_id=p.department_id",
                  "CROSS JOIN fixture_template_types t",
                  "WHERE t.type_no IN (3,4) OR (p.probationary_position=1 AND t.type_no IN (1,2));",
                  "", "INSERT INTO evaluation_template_positions (template_id,job_title_id)",
                  "SELECT t.template_id,t.target_job_title_id FROM evaluation_templates t",
                  "JOIN fixture_ready_targets p ON p.job_title_id=t.target_job_title_id",
                  "WHERE t.template_id BETWEEN 200000 AND 219999;",
                  "", "INSERT INTO evaluation_criteria",
                  "  (template_id,section,criterion_name,description,weight,scoring_method,sort_order,is_custom)",
                  "SELECT t.template_id,'KRA',CONCAT(j.job_title,' - ',k.name),k.description,",
                  "  k.weight,'Scale_1_4',k.sort_order,0",
                  "FROM evaluation_templates t",
                  "JOIN fixture_ready_targets p ON p.job_title_id=t.target_job_title_id",
                  "JOIN job_titles j ON j.job_title_id=p.job_title_id",
                  "JOIN fixture_template_kras k ON k.department_id=p.department_id",
                  "WHERE t.template_id BETWEEN 200000 AND 219999;",
                  "", "INSERT INTO evaluation_criteria",
                  "  (template_id,section,criterion_name,kpi_description,weight,scoring_method,sort_order,is_custom)",
                  "SELECT t.template_id,'Behavior',b.name,b.kpi,0,'Scale_1_4',12+b.sort_order,0",
                  "FROM evaluation_templates t",
                  "JOIN fixture_ready_targets p ON p.job_title_id=t.target_job_title_id",
                  "CROSS JOIN fixture_template_behaviors b",
                  "WHERE t.template_id BETWEEN 200000 AND 219999;",
                  "", "DROP TEMPORARY TABLE fixture_ready_targets;",
                  "DROP TEMPORARY TABLE fixture_template_types;",
                  "DROP TEMPORARY TABLE fixture_template_behaviors;",
                  "DROP TEMPORARY TABLE fixture_template_kras;",
                  "DROP TEMPORARY TABLE fixture_template_positions;", ""])
    return "\n".join(lines)


if __name__ == "__main__":
    output = Path(__file__).with_name("01_seed_templates.sql")
    output.write_text(build(), encoding="utf-8")
    print(f"Wrote {output}")
