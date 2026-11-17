-- GENERAL SERVICES ANNUAL DRY RUN
-- Base period: 2026-01-01 to 2026-12-31; all 24 GS positions.
-- Facilities Maintenance post-probation period: 2027-01-01 to 2027-12-31.
-- Templates only. No scores, submissions, approvals, or assignments.
-- Annual self-rating requires an Approved Quarterly in the same year.
-- Actual period starts on the later of Jan 1 or regularization date.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_general_services_annual_dry_run;
DELIMITER //
CREATE PROCEDURE seed_general_services_annual_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_vp INT;
  DECLARE v_mgr INT;
  DECLARE v_sup INT;
  DECLARE v_vp_form INT;
  DECLARE v_mgr_form INT;
  DECLARE v_sup_form INT;
  DECLARE v_driver_form INT;
  DECLARE v_security_form INT;
  DECLARE v_fac_form INT;
  DECLARE v_warehouse_form INT;
  DECLARE v_messenger_form INT;
  DECLARE v_fac_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('GS-VP-A-DRYRUN-2026',
     'GS-MGR-A-DRYRUN-2026',
     'GS-SUP-A-DRYRUN-2026',
     'GS-DRIVER-A-DRYRUN-2026',
     'GS-SECURITY-A-DRYRUN-2026',
     'GS-FAC-A-DRYRUN-2026',
     'GS-WAREHOUSE-A-DRYRUN-2026',
     'GS-MESSENGER-A-DRYRUN-2026',
     'GS-FAC-A-DRYRUN-2027')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='GS Annual form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=6 AND job_title_id BETWEEN 600 AND 623 AND is_active=1)<>24
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=600 AND reports_to=1100 AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=6 AND job_title_id BETWEEN 601 AND 604 AND reports_to=600 AND is_active=1)<>4
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=6 AND job_title_id BETWEEN 605 AND 608 AND reports_to=601 AND is_active=1)<>4
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=6 AND job_title_id BETWEEN 609 AND 623 AND reports_to=605 AND is_active=1)<>15 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected all 24 GS positions and reporting lines';
  END IF;

  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_vp FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-GS-V' AND e.job_title_id=600 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND e.department_id=6 AND e.employment_status='Regular';
  SELECT MIN(u.user_id) INTO v_mgr FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-GS-M' AND e.job_title_id=601 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND e.department_id=6 AND e.employment_status='Regular';
  SELECT MIN(u.user_id) INTO v_sup FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-GS-H' AND e.job_title_id=605 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND e.department_id=6 AND e.employment_status='Regular';
  IF v_ceo IS NULL OR v_vp IS NULL OR v_mgr IS NULL OR v_sup IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active CEO, GS VP, Manager, and Supervisor portal accounts are required';
  END IF;

  START TRANSACTION;
  -- Eight role-specific forms cover every active GS position.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - VP for General Services - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,600,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-VP-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Annual - GS Managers I-IV - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,601,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-MGR-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_vp),
    ('[DRY RUN] Annual - GS Supervisors I-IV - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,605,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-SUP-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_mgr),
    ('[DRY RUN] Annual - Drivers I-V - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,609,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-DRIVER-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_sup),
    ('[DRY RUN] Annual - Security Monitoring Staff I-IV - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,614,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-SECURITY-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_sup),
    ('[DRY RUN] Annual - Facilities Maintenance Staff I-III - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,618,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-FAC-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_sup),
    ('[DRY RUN] Annual - Warehouse Staff I - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,621,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-WAREHOUSE-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_sup),
    ('[DRY RUN] Annual - Messengers I-II - Main Office',
     'General Services role performance and documented work outputs.',
     'General Services',6,622,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'GS-MESSENGER-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_sup);
  SELECT template_id INTO v_vp_form FROM evaluation_templates WHERE form_code='GS-VP-A-DRYRUN-2026';
  SELECT template_id INTO v_mgr_form FROM evaluation_templates WHERE form_code='GS-MGR-A-DRYRUN-2026';
  SELECT template_id INTO v_sup_form FROM evaluation_templates WHERE form_code='GS-SUP-A-DRYRUN-2026';
  SELECT template_id INTO v_driver_form FROM evaluation_templates WHERE form_code='GS-DRIVER-A-DRYRUN-2026';
  SELECT template_id INTO v_security_form FROM evaluation_templates WHERE form_code='GS-SECURITY-A-DRYRUN-2026';
  SELECT template_id INTO v_fac_form FROM evaluation_templates WHERE form_code='GS-FAC-A-DRYRUN-2026';
  SELECT template_id INTO v_warehouse_form FROM evaluation_templates WHERE form_code='GS-WAREHOUSE-A-DRYRUN-2026';
  SELECT template_id INTO v_messenger_form FROM evaluation_templates WHERE form_code='GS-MESSENGER-A-DRYRUN-2026';

  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE
    WHEN job_title_id =600 THEN v_vp_form
    WHEN job_title_id BETWEEN 601 AND 604 THEN v_mgr_form
    WHEN job_title_id BETWEEN 605 AND 608 THEN v_sup_form
    WHEN job_title_id BETWEEN 609 AND 613 THEN v_driver_form
    WHEN job_title_id BETWEEN 614 AND 617 THEN v_security_form
    WHEN job_title_id BETWEEN 618 AND 620 THEN v_fac_form
    WHEN job_title_id =621 THEN v_warehouse_form
    ELSE v_messenger_form END,job_title_id
  FROM job_titles WHERE department_id=6 AND job_title_id BETWEEN 600 AND 623 AND is_active=1;

  -- Each group has eight KRAs totaling 100% inside the KRA section.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    -- VP for General Services
    (v_vp_form,'KRA','Service strategy','Set documented GS service priorities and delivery targets.','Set documented GS service priorities and delivery targets.',20,'Scale_1_4',1),
    (v_vp_form,'KRA','Budget stewardship','Monitor approved facilities and logistics spending.','Monitor approved facilities and logistics spending.',15,'Scale_1_4',2),
    (v_vp_form,'KRA','Facilities risk oversight','Direct action on material building, safety, and continuity risks.','Direct action on material building, safety, and continuity risks.',15,'Scale_1_4',3),
    (v_vp_form,'KRA','Logistics governance','Review transport, warehouse, and messenger service performance.','Review transport, warehouse, and messenger service performance.',15,'Scale_1_4',4),
    (v_vp_form,'KRA','Control compliance','Ensure required asset, security, and vendor controls are followed.','Ensure required asset, security, and vendor controls are followed.',10,'Scale_1_4',5),
    (v_vp_form,'KRA','Manager leadership','Review GS manager delivery and development actions.','Review GS manager delivery and development actions.',10,'Scale_1_4',6),
    (v_vp_form,'KRA','Executive reporting','Report service risks, results, and decisions needed on schedule.','Report service risks, results, and decisions needed on schedule.',10,'Scale_1_4',7),
    (v_vp_form,'KRA','Improvement planning','Sponsor documented service and cost improvements.','Sponsor documented service and cost improvements.',5,'Scale_1_4',8),
    -- GS Managers I-IV
    (v_mgr_form,'KRA','Service plan delivery','Translate GS priorities into team assignments and milestones.','Translate GS priorities into team assignments and milestones.',20,'Scale_1_4',1),
    (v_mgr_form,'KRA','Facilities coordination','Oversee maintenance requests and service restoration.','Oversee maintenance requests and service restoration.',15,'Scale_1_4',2),
    (v_mgr_form,'KRA','Transport oversight','Review fleet availability and assigned trip performance.','Review fleet availability and assigned trip performance.',15,'Scale_1_4',3),
    (v_mgr_form,'KRA','Inventory control','Monitor supplies and asset custody exceptions.','Monitor supplies and asset custody exceptions.',15,'Scale_1_4',4),
    (v_mgr_form,'KRA','Vendor performance','Check contracted services against approved terms.','Check contracted services against approved terms.',10,'Scale_1_4',5),
    (v_mgr_form,'KRA','Supervisor coaching','Review GS supervisor work and document feedback.','Review GS supervisor work and document feedback.',10,'Scale_1_4',6),
    (v_mgr_form,'KRA','Risk escalation','Escalate safety, security, and continuity issues promptly.','Escalate safety, security, and continuity issues promptly.',10,'Scale_1_4',7),
    (v_mgr_form,'KRA','VP reporting','Submit accurate GS status and variance reports.','Submit accurate GS status and variance reports.',5,'Scale_1_4',8),
    -- GS Supervisors I-IV
    (v_sup_form,'KRA','Daily work assignment','Assign frontline service work with priorities and deadlines.','Assign frontline service work with priorities and deadlines.',20,'Scale_1_4',1),
    (v_sup_form,'KRA','Work order review','Check completion and evidence for assigned service requests.','Check completion and evidence for assigned service requests.',15,'Scale_1_4',2),
    (v_sup_form,'KRA','Safety checks','Monitor safe work practices and escalate hazards.','Monitor safe work practices and escalate hazards.',15,'Scale_1_4',3),
    (v_sup_form,'KRA','Service response','Resolve or escalate delayed internal support requests.','Resolve or escalate delayed internal support requests.',15,'Scale_1_4',4),
    (v_sup_form,'KRA','Staff coaching','Review frontline outputs and give specific feedback.','Review frontline outputs and give specific feedback.',10,'Scale_1_4',5),
    (v_sup_form,'KRA','Asset custody','Check tool, vehicle, and supply handoff records.','Check tool, vehicle, and supply handoff records.',10,'Scale_1_4',6),
    (v_sup_form,'KRA','Cross-team coordination','Coordinate access, schedules, and service disruptions.','Coordinate access, schedules, and service disruptions.',10,'Scale_1_4',7),
    (v_sup_form,'KRA','Manager updates','Report progress and exceptions on time.','Report progress and exceptions on time.',5,'Scale_1_4',8),
    -- Drivers I-V
    (v_driver_form,'KRA','Assigned trip delivery','Complete authorized trips on time and by approved routes.','Complete authorized trips on time and by approved routes.',20,'Scale_1_4',1),
    (v_driver_form,'KRA','Vehicle safety inspection','Perform and document required pre-trip checks.','Perform and document required pre-trip checks.',15,'Scale_1_4',2),
    (v_driver_form,'KRA','Trip documentation','Keep complete mileage, fuel, and passenger or cargo logs.','Keep complete mileage, fuel, and passenger or cargo logs.',15,'Scale_1_4',3),
    (v_driver_form,'KRA','Road safety','Follow traffic rules and defensive driving practices.','Follow traffic rules and defensive driving practices.',15,'Scale_1_4',4),
    (v_driver_form,'KRA','Vehicle care','Report defects and arrange approved maintenance promptly.','Report defects and arrange approved maintenance promptly.',10,'Scale_1_4',5),
    (v_driver_form,'KRA','Passenger service','Communicate schedule changes and assist passengers professionally.','Communicate schedule changes and assist passengers professionally.',10,'Scale_1_4',6),
    (v_driver_form,'KRA','Cargo custody','Protect assigned documents, goods, and vehicle contents.','Protect assigned documents, goods, and vehicle contents.',10,'Scale_1_4',7),
    (v_driver_form,'KRA','Incident reporting','Report accidents and unusual events without delay.','Report accidents and unusual events without delay.',5,'Scale_1_4',8),
    -- Security Monitoring Staff I-IV
    (v_security_form,'KRA','Monitoring coverage','Maintain assigned monitoring posts and schedules.','Maintain assigned monitoring posts and schedules.',20,'Scale_1_4',1),
    (v_security_form,'KRA','Incident detection','Identify unusual activity and escalate according to protocol.','Identify unusual activity and escalate according to protocol.',15,'Scale_1_4',2),
    (v_security_form,'KRA','Access record accuracy','Record authorized entry, exit, and visitor activity correctly.','Record authorized entry, exit, and visitor activity correctly.',15,'Scale_1_4',3),
    (v_security_form,'KRA','Equipment checks','Check cameras, alarms, and monitoring tools and report faults.','Check cameras, alarms, and monitoring tools and report faults.',15,'Scale_1_4',4),
    (v_security_form,'KRA','Response coordination','Provide timely information during security incidents.','Provide timely information during security incidents.',10,'Scale_1_4',5),
    (v_security_form,'KRA','Evidence preservation','Protect and document relevant incident records.','Protect and document relevant incident records.',10,'Scale_1_4',6),
    (v_security_form,'KRA','Confidentiality','Handle surveillance and personal information appropriately.','Handle surveillance and personal information appropriately.',10,'Scale_1_4',7),
    (v_security_form,'KRA','Shift handoff','Provide complete logs and open issues to the next shift.','Provide complete logs and open issues to the next shift.',5,'Scale_1_4',8),
    -- Facilities Maintenance Staff I-III
    (v_fac_form,'KRA','Work order completion','Complete assigned maintenance work by agreed deadlines.','Complete assigned maintenance work by agreed deadlines.',20,'Scale_1_4',1),
    (v_fac_form,'KRA','Preventive maintenance','Perform scheduled checks and document findings.','Perform scheduled checks and document findings.',15,'Scale_1_4',2),
    (v_fac_form,'KRA','Repair quality','Resolve reported defects without unresolved material issues.','Resolve reported defects without unresolved material issues.',15,'Scale_1_4',3),
    (v_fac_form,'KRA','Safety compliance','Use required safety procedures and protective equipment.','Use required safety procedures and protective equipment.',15,'Scale_1_4',4),
    (v_fac_form,'KRA','Tool and material control','Record use and custody of tools and supplies.','Record use and custody of tools and supplies.',10,'Scale_1_4',5),
    (v_fac_form,'KRA','Fault escalation','Escalate hazards and repairs beyond authorized scope.','Escalate hazards and repairs beyond authorized scope.',10,'Scale_1_4',6),
    (v_fac_form,'KRA','Service communication','Update requesters on work status and access needs.','Update requesters on work status and access needs.',10,'Scale_1_4',7),
    (v_fac_form,'KRA','Work records','Keep complete maintenance logs and handoff notes.','Keep complete maintenance logs and handoff notes.',5,'Scale_1_4',8),
    -- Warehouse Staff I
    (v_warehouse_form,'KRA','Inventory accuracy','Record receipts, issues, and balances correctly.','Record receipts, issues, and balances correctly.',20,'Scale_1_4',1),
    (v_warehouse_form,'KRA','Receiving checks','Verify quantity and condition against approved documents.','Verify quantity and condition against approved documents.',15,'Scale_1_4',2),
    (v_warehouse_form,'KRA','Issue control','Release items only with required authorization.','Release items only with required authorization.',15,'Scale_1_4',3),
    (v_warehouse_form,'KRA','Stock organization','Store and label items for safe retrieval.','Store and label items for safe retrieval.',15,'Scale_1_4',4),
    (v_warehouse_form,'KRA','Reconciliation support','Investigate count differences and document corrections.','Investigate count differences and document corrections.',10,'Scale_1_4',5),
    (v_warehouse_form,'KRA','Custody and safety','Protect inventory and follow storage safety rules.','Protect inventory and follow storage safety rules.',10,'Scale_1_4',6),
    (v_warehouse_form,'KRA','Service response','Fulfill approved internal requests by agreed deadlines.','Fulfill approved internal requests by agreed deadlines.',10,'Scale_1_4',7),
    (v_warehouse_form,'KRA','Record completeness','Maintain traceable stock and handoff records.','Maintain traceable stock and handoff records.',5,'Scale_1_4',8),
    -- Messengers I-II
    (v_messenger_form,'KRA','Delivery timeliness','Complete authorized pickups and deliveries on schedule.','Complete authorized pickups and deliveries on schedule.',20,'Scale_1_4',1),
    (v_messenger_form,'KRA','Document custody','Protect assigned documents and packages in transit.','Protect assigned documents and packages in transit.',15,'Scale_1_4',2),
    (v_messenger_form,'KRA','Receipt accuracy','Obtain complete acknowledgments and delivery records.','Obtain complete acknowledgments and delivery records.',15,'Scale_1_4',3),
    (v_messenger_form,'KRA','Route coordination','Plan approved trips and report delays promptly.','Plan approved trips and report delays promptly.',15,'Scale_1_4',4),
    (v_messenger_form,'KRA','Confidentiality','Handle sensitive documents according to policy.','Handle sensitive documents according to policy.',10,'Scale_1_4',5),
    (v_messenger_form,'KRA','Service communication','Update requesters on delivery status and exceptions.','Update requesters on delivery status and exceptions.',10,'Scale_1_4',6),
    (v_messenger_form,'KRA','Safety compliance','Follow transport and workplace safety rules.','Follow transport and workplace safety rules.',10,'Scale_1_4',7),
    (v_messenger_form,'KRA','Handoff records','Maintain traceable pickup and recipient details.','Maintain traceable pickup and recipient details.',5,'Scale_1_4',8);

  -- Shared Core Behaviors contribute 20% to the overall score.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_vp_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_vp_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_vp_form,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_vp_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_vp_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_vp_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_vp_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_vp_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT target.template_id,c.section,c.criterion_name,c.description,c.kpi_description,c.weight,c.scoring_method,c.sort_order
  FROM evaluation_criteria c CROSS JOIN
    (SELECT v_mgr_form AS template_id UNION ALL SELECT v_sup_form UNION ALL SELECT v_driver_form UNION ALL SELECT v_security_form UNION ALL SELECT v_fac_form UNION ALL SELECT v_warehouse_form UNION ALL SELECT v_messenger_form) target
  WHERE c.template_id=v_vp_form AND c.section='Behavior';

  -- Additional Facilities Maintenance form for Paolo and Jerome after probation.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - Facilities Maintenance - Post-Probation 2027',
     'Regular Facilities Maintenance Staff I-III review after probation.',
     'General Services',6,618,'ANNUAL-CORE','1','Annual',1,
     '2027-01-01','2027-12-31',80,20,'GS-FAC-A-DRYRUN-2027',
     '2026-11-17','2027-01-01','Active',v_sup);
  SET v_fac_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_fac_2027_form,job_title_id FROM job_titles
  WHERE department_id=6 AND job_title_id BETWEEN 618 AND 620 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_fac_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_fac_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN (v_vp_form,v_mgr_form,v_sup_form,v_driver_form,v_security_form,v_fac_form,v_warehouse_form,v_messenger_form,v_fac_2027_form))<>27
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN (v_vp_form,v_mgr_form,v_sup_form,v_driver_form,v_security_form,v_fac_form,v_warehouse_form,v_messenger_form,v_fac_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='GS Annual position or criteria validation failed';
  END IF;
  COMMIT;
  SELECT 'GS Annual forms imported' AS result,9 AS forms,24 AS covered_positions;
END //
DELIMITER ;
CALL seed_general_services_annual_dry_run();
DROP PROCEDURE seed_general_services_annual_dry_run;

