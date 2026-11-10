"""Build the standalone SQL fixture. Standard library only; never connects to MySQL."""
from pathlib import Path
from datetime import date, timedelta
from decimal import Decimal, ROUND_HALF_UP
import json
import re

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
AS_OF = date(2026, 11, 10)
PREFIX = 'D26'

def round2(value):
    return float(Decimal(str(value)).quantize(Decimal('.01'), rounding=ROUND_HALF_UP))

def plus_months(day, months):
    month = day.month - 1 + months
    return date(day.year + month // 12, month % 12 + 1, day.day)

class Raw(str):
    pass

def sql(value):
    if isinstance(value, Raw): return str(value)
    if value is None: return 'NULL'
    if isinstance(value, bool): return str(int(value))
    if isinstance(value, (int, float)): return str(value)
    if isinstance(value, (list, dict)): value = json.dumps(value, ensure_ascii=False, separators=(',', ':'))
    return "'" + str(value).replace('\\', '\\\\').replace("'", "''") + "'"

out = []
def emit(line=''): out.append(line)
def insert(table, **row):
    emit(f"INSERT INTO {table} ({','.join(row)}) VALUES ({','.join(map(sql,row.values()))});")
def lookup(table, column, **where):
    return Raw('(SELECT '+column+' FROM '+table+' WHERE '+' AND '.join(k+'='+sql(v) for k,v in where.items())+')')

org = (ROOT/'database/2nd_seed_organization.sql').read_text(encoding='utf-8-sig')
positions = {}
for match in re.finditer(r"\((\d+),\s*'([^']+)',\s*(\d+),\s*(\d+),\s*1,\s*[01],\s*(NULL|\d+),\s*NOW\(\),\s*NOW\(\)\)", org):
    pid, title, rank, dept, parent = match.groups()
    positions[title] = dict(id=int(pid), title=title, rank=int(rank), dept=int(dept), parent=None if parent=='NULL' else int(parent))
departments = dict((int(i), name) for i,name in re.findall(r"\((\d+), '([^']+)', '[^']*', 1, NOW\(\), NOW\(\)\)", org) if int(i)<=12 and name in ['Acquired Properties','Audit','Business Development','Compliance','Finance','General Services','Human Resources','Information Technology','Marketing','Office of the President','Operations','Purchasing'])
assert len(departments)==12

# These are references to the actual organization seed, never invented positions.
roster = [
('President and CEO',2001,'Isadora','Velasco','Main Office'),
('VP for Acquired Properties',2013,'Leandro','Mirasol','Main Office'),
('AP Manager I',2015,'Amara','Bautista','Main Office'),
('AP Supervisor I',2017,'Rafael','Buenaventura','Main Office'),
('AP Staff I',2021,'Celina','Manalastas','Pagbilao Branch'),
('AP Staff II',2024,'Nico','Almeda','Calamba Branch 1'),
('Audit Manager I',2012,'Esteban','Villareal','Main Office'),
('Audit Manager II',2014,'Mireya','Salcedo','Main Office'),
('Audit Supervisor I',2016,'Gabriel','De Vera','Main Office'),
('Auditor I',2021,'Althea','Macalintal','Lucban Branch 1'),
('Auditor II',2024,'Dario','Tolentino','Lipa Branch 1'),
('Business Development Officer I',2013,'Beatriz','Fuentebella','Main Office'),
('Business Development Staff I',2020,'Tomas','Magsino','Candelaria Branch 1'),
('Business Development Staff II',2022,'Inez','Sarmiento','Calamba Branch 1'),
('Business Development Staff I',2024,'Paolo','Carandang','Lipa Branch 1'),
('Compliance Supervisor I',2012,'Lucia','Montelibano','Main Office'),
('Compliance Supervisor II',2015,'Mateo','Solis','Main Office'),
('Compliance Staff I',2020,'Sabina','Espinosa','Pagbilao Branch'),
('Compliance Staff II',2024,'Emilio','Abad','Atimonan Branch 1'),
('VP for Finance',2011,'Carmela','Ledesma','Main Office'),
('Accounting Manager I',2014,'Andres','Mercado','Main Office'),
('Accounting Supervisor I',2016,'Teresa','Marasigan','Main Office'),
('Treasury Supervisor I',2015,'Dante','Feliciano','Main Office'),
('Accounting Staff I',2021,'Mila','Panganiban','Candelaria Branch 1'),
('Treasury Staff I',2024,'Enzo','Capistrano','Lucban Branch 1'),
('VP for General Services',2012,'Valeria','Serrano','Main Office'),
('GS Manager I',2014,'Ramon','Alegre','Main Office'),
('GS Supervisor I',2017,'Nerissa','Dimaculangan','Main Office'),
('Facilities Maintenance Staff I',2022,'Julian','Balmaceda','Lipa Branch 1'),
('Driver I',2024,'Rosana','Ilagan','Atimonan Branch 1'),
('HR Manager II',2021,'Flora','Alcantara','Main Office'),
('HR Supervisor II',2022,'Lorenzo','Mallari','Main Office'),
('HR Staff I',2023,'Clarissa','Samonte','Main Office'),
('HR Staff III',2024,'Adrian','Dimalanta','Main Office'),
('IT Manager I',2014,'Victor','Manansala','Main Office'),
('IT Supervisor I',2017,'Elisa','Agoncillo','Main Office'),
('Programmer I',2020,'Marco','Labastida','Main Office'),
('Technical Support Staff I',2023,'Sofia','Balagtas','Calamba Branch 1'),
('Technical Support Staff II',2024,'Renzo','Lacson','Pagbilao Branch'),
('Marketing Manager I',2014,'Aurora','Tiongson','Main Office'),
('Marketing Supervisor I',2017,'Cesar','Magtoto','Main Office'),
('Marketing Staff I',2021,'Liana','Francisco','Lucban Branch 1'),
('Marketing Staff II',2024,'Oscar','Dalisay','Lipa Branch 1'),
('Executive Assistant I',2020,'Marina','Delos Reyes','Main Office'),
('Executive Assistant II',2022,'Felix','Escudero','Main Office'),
('Executive Assistant III',2024,'Carina','Buenaflor','Main Office'),
('VP for Operations',2011,'Roberto','Lazatin','Main Office'),
('Regional Manager I',2014,'Dalia','Sicat','Main Office'),
('Area Coordinator I',2017,'Antonio','Hizon','Candelaria Branch 1'),
('Focal Person I',2019,'Celia','Magbanua','Calamba Branch 1'),
('Branch Staff I',2021,'Luis','Natividad','Atimonan Branch 1'),
('Branch Staff II',2024,'Mara','Cabral','Pagbilao Branch'),
('Procurement Supervisor I',2017,'Daniel','Ongpin','Main Office'),
('Purchasing Staff I',2022,'Nadia','Rosales','Candelaria Branch 1'),
('Purchasing Staff I',2024,'Joel','Gatdula','Calamba Branch 1'),
]
people = []
for n,(title,year,first,last,branch) in enumerate(roster,1):
    pos=positions[title]
    hire=date(year, 1+(n%4), 5+(n%12))
    if title=='President and CEO': hire=date(2001,1,15)
    p=dict(n=n,id=910000+n,uid=920000+n,portal=921000+n,code=f'D26-{pos["dept"]:02}-{n:03}',
        title=title,first=first,last=last,middle=['Reyes','Santos','Cruz','Navarro','Rivera'][n%5],
        dept=pos['dept'],rank=pos['rank'],position=pos,hire=hire,reg=plus_months(hire,5)+timedelta(days=5),
        branch='Raquel Pawnshop Main Office' if branch=='Main Office' else branch,
        dob=date(year-(34 if pos['rank']==1 else 29 if pos['rank']==3 else 26 if pos['rank']==4 else 24),1+n%10,3+n%20),
        base=False)
    p['role']='President and CEO' if title=='President and CEO' else ('HR Manager' if pos['dept']==7 and pos['rank']==3 else 'HR Supervisor' if pos['dept']==7 and pos['rank']==4 else 'HR Staff' if pos['dept']==7 else 'Employee')
    p['gender']='Female' if first in {'Isadora','Amara','Celina','Mireya','Althea','Beatriz','Inez','Lucia','Sabina','Carmela','Teresa','Mila','Valeria','Nerissa','Rosana','Flora','Clarissa','Elisa','Sofia','Aurora','Liana','Marina','Carina','Dalia','Celia','Mara','Nadia'} else 'Male'
    if pos['dept']!=7: p['portal']=p['uid']
    people.append(p)
base_hr=[
dict(n=101,id=101,uid=2,portal=921101,code='HRD-001',title='HR Manager I',first='Elena',last='Delgado',middle='Santos',dept=7,rank=3,hire=date(2020,1,15)),
dict(n=102,id=301,uid=3,portal=921102,code='HRD-002',title='HR Supervisor I',first='Patricia',last='Gomez',middle='Luna',dept=7,rank=4,hire=date(2017,2,14)),
dict(n=103,id=302,uid=4,portal=921103,code='HRD-003',title='HR Staff II',first='Miguel',last='Torres',middle='Cruz',dept=7,rank=5,hire=date(2022,1,5)),
]
for p in base_hr:
    p.update(base=True,position=positions[p['title']],role=['HR Manager','HR Supervisor','HR Staff'][p['rank']-3],branch='Raquel Pawnshop Main Office',reg=plus_months(p['hire'],5))
    # Keep the four original base accounts untouched. These additional demo actor
    # accounts carry historical issuance dates rather than the base import's NOW().
    p['uid']=922000+p['n']
all_people=people+base_hr
by_position={}
for p in all_people: by_position.setdefault(p['position']['id'],p)
for p in all_people:
    p['head']=by_position.get(p['position']['parent'])
    if p['title']!='President and CEO': assert p['head'] and (p['base'] or p['head']['reg']<p['hire']), (p['title'],p.get('head'))
groups={d:[p for p in all_people if p['dept']==d and p['title']!='President and CEO'] for d in departments}
owners={1:'AP Manager I',2:'Audit Manager I',3:'Business Development Officer I',4:'Compliance Supervisor I',5:'VP for Finance',6:'VP for General Services',7:'HR Manager I',8:'IT Manager I',9:'Marketing Manager I',10:'President and CEO',11:'VP for Operations',12:'Procurement Supervisor I'}
owner_people={d:next(p for p in all_people if p['title']==title) for d,title in owners.items()}
ceo=people[0]
for p in all_people:
    p['probhead']=p['head']
    if p['head'] and p['dept']!=7 and p['head']['rank']>=p['rank']:
        p['probhead']=ceo  # createOrganizationPackageRoute's equal-rank fallback.

# Each pair is a distinct work outcome and measurable acceptance threshold.
domains={
1:[('Property register','Reconcile 100% of assigned property records monthly; resolve differences within 5 working days'),('Title completeness','Verify title and tax documents for every assigned case before endorsement'),('Valuation evidence','Obtain two current comparable transactions for each valuation recommendation'),('Disposition milestones','Complete at least 90% of approved disposition milestones by due date'),('Buyer due diligence','Complete identity and funding checks before every buyer endorsement'),('Site inspection','Document condition, occupancy and hazards for every scheduled inspection'),('Occupancy monitoring','Update occupied-property follow-ups weekly and escalate overdue cases within 2 days'),('Broker coordination','Record broker leads and follow up qualified enquiries within 2 working days'),('Tax obligations','Maintain a deadline register with no missed assigned tax filing dates'),('Case documentation','Index all signed case documents within 2 working days of receipt'),('Collection tracking','Reconcile scheduled buyer payments weekly and flag arrears within 1 day'),('Portfolio reporting','Deliver monthly portfolio report by the fifth working day with no material errors'),('Client enquiries','Answer at least 95% of assigned client enquiries within 2 working days'),('Legal escalation','Refer all identified legal exceptions with complete evidence within 2 days'),('Asset safeguarding','Close critical property safety findings within approved response dates'),('Expense control','Explain all acquisition/disposition cost variances exceeding 5%'),('Team delivery','Review assigned case workloads fortnightly and redistribute overdue tasks'),('Policy implementation','Document quarterly compliance checks for every assigned property process')],
2:[('Audit workpapers','Complete evidence cross-references for every sampled transaction'),('Control testing','Execute 100% of the approved assigned control tests'),('Exception validation','Validate every finding against source documents before issue'),('Risk assessment','Update risk ratings for all assigned areas each quarter'),('Sampling coverage','Meet approved sample sizes and document exclusions for every engagement'),('Fieldwork deadlines','Complete at least 90% of fieldwork milestones on schedule'),('Finding documentation','Record condition, cause, impact and evidence for each finding'),('Corrective action follow-up','Verify closure evidence for all actions due this quarter'),('Report accuracy','Issue reports with no unsupported material conclusions'),('Confidential records','Maintain access logs and secure all audit evidence daily'),('Fraud indicators','Escalate credible red flags with supporting evidence within 1 working day'),('Branch reconciliation','Reconcile assigned cash and inventory samples without unexplained differences'),('Audit coordination','Confirm branch schedules at least 5 working days before fieldwork'),('Audit plan coverage','Review quarterly coverage against approved high-risk priorities'),('Quality review','Complete documented supervisory checks before each final report'),('Stakeholder response','Resolve management clarification requests within 3 working days'),('Audit resources','Keep engagement hours within approved budget or document variances'),('Control improvement','Track measurable control improvements after each completed engagement')],
3:[('Market mapping','Maintain current competitor and demand profiles for all assigned territories'),('Lead qualification','Verify contact, need and location for every endorsed opportunity'),('Site feasibility','Complete the approved feasibility checklist for each proposed site'),('Business case evidence','Support forecasts with documented assumptions and source data'),('Partner outreach','Complete the agreed monthly qualified partner-contact target'),('Proposal turnaround','Submit at least 90% of assigned proposals by their agreed due date'),('Pipeline hygiene','Update opportunity stages and next actions every week'),('Pilot monitoring','Track pilot costs and conversion indicators fortnightly'),('Customer research','Document at least 10 usable customer observations per assigned market study'),('Expansion records','Maintain complete approval and lease files for all assigned sites'),('Competitor pricing','Validate competitor pricing and service observations each month'),('Progress reporting','Submit the monthly expansion dashboard by the fifth working day'),('Follow-up discipline','Contact qualified leads within 2 working days of assignment'),('Investment prioritization','Compare each opportunity against the approved return and risk thresholds'),('Launch readiness','Close all critical readiness actions before opening endorsement'),('Partnership review','Review partner service commitments quarterly using recorded outcomes'),('Budget discipline','Explain project spending variances greater than 5% each month'),('Team capability','Deliver one documented market-learning session each quarter')],
4:[('Regulatory register','Maintain a current register of all assigned regulatory obligations'),('Filing preparation','Prepare every assigned return at least 3 working days before deadline'),('KYC review','Check all required customer documents for every assigned sample'),('AML monitoring','Escalate all suspicious transaction exceptions within policy deadlines'),('Compliance evidence','Index all compliance evidence within 2 working days'),('Branch checklist','Complete 100% of scheduled assigned branch compliance checks'),('Exception tracking','Update owners and target dates for every open compliance issue weekly'),('Policy distribution','Record acknowledgement of each new assigned policy release'),('Training records','Maintain attendance and quiz evidence for all assigned compliance sessions'),('Regulatory enquiries','Provide complete source-backed responses within the agreed deadline'),('Data protection','Log and escalate all identified privacy incidents within 1 working day'),('License renewals','Track all assigned renewals with no unreported expired requirement'),('Corrective evidence','Verify evidence before closing each compliance action'),('Regulatory change assessment','Assess every material regulatory change within 10 working days'),('Compliance assurance','Review exception trends and mitigation effectiveness quarterly'),('Management briefing','Submit a concise risk and overdue-obligation briefing each month'),('Escalation quality','Escalate overdue high-risk issues with documented impact and owner'),('Control ownership','Assign and review controls for every material compliance obligation')],
5:[('Ledger accuracy','Reconcile every assigned ledger account monthly with supporting schedules'),('Transaction posting','Post at least 98% of complete transactions within 2 working days'),('Bank reconciliation','Complete assigned reconciliations by the fifth working day'),('Supporting documents','Match invoices, approvals and receipts before every posting'),('Payment validation','Verify payee and payment authority for every disbursement'),('Cash position','Update assigned cash position schedules each banking day'),('Receivable follow-up','Record action on every overdue assigned receivable each week'),('Tax schedules','Prepare assigned tax schedules at least 3 days before filing deadline'),('Expense classification','Maintain at least 98% accuracy in reviewed expense classifications'),('Interbranch balances','Clear or explain every material interbranch difference monthly'),('Month-end support','Complete assigned closing tasks within the approved close timetable'),('Financial schedules','Deliver source-reconciled management schedules by due date'),('Financial records','File all assigned financial records with complete reference numbers'),('Liquidity planning','Review rolling cash forecasts weekly and explain material gaps'),('Financial controls','Document monthly approval and segregation-of-duty checks'),('Budget review','Investigate every department budget variance exceeding 5%'),('Risk monitoring','Maintain action plans for every high-risk finance exception'),('Team review','Complete documented review of all material monthly schedules')],
6:[('Service requests','Close at least 90% of assigned service requests within agreed response times'),('Asset register','Reconcile assigned asset custody records monthly'),('Preventive maintenance','Complete 100% of scheduled preventive tasks or document approved deferrals'),('Safety checks','Record and escalate every critical safety finding on the inspection day'),('Supplier service','Verify completion evidence before endorsing supplier invoices'),('Inventory counts','Complete assigned monthly counts and explain all material differences'),('Repair documentation','Record diagnosis, materials and completion evidence for every repair'),('Facility inspections','Complete each scheduled inspection using the approved checklist'),('Dispatch records','Maintain complete purpose, authorization and return records for every dispatch'),('Utilities monitoring','Record monthly consumption and investigate changes exceeding 10%'),('Equipment care','Follow approved inspection and cleaning schedules for all assigned equipment'),('Cost records','Record actual material and service costs against each work order'),('Service coordination','Confirm priority and target date for each assigned request'),('Facilities planning','Review quarterly maintenance capacity against the branch service plan'),('Vendor evaluation','Review critical vendor performance against service commitments quarterly'),('Fleet oversight','Review vehicle availability and safety exceptions every month'),('Resource allocation','Publish weekly task assignments and follow up overdue work'),('Continuity readiness','Document and test the assigned service continuity arrangements quarterly')],
7:[('Personnel file accuracy','Maintain at least 98% completeness in sampled employee files'),('Recruitment coordination','Schedule qualified interviews within 3 working days of endorsement'),('Onboarding records','Complete the onboarding checklist for every assigned new hire'),('Attendance validation','Reconcile attendance exceptions before each payroll cutoff'),('Benefits documentation','Prepare complete benefit applications within 2 working days of receipt'),('Employee enquiries','Resolve at least 90% of assigned routine enquiries within 2 working days'),('Contract tracking','Flag all assigned expiring contracts at least 30 days before expiry'),('Evaluation monitoring','Update assigned evaluation status and deadlines every week'),('Training coordination','Complete attendance and feedback records for every assigned session'),('HR data privacy','Maintain authorized access and disclosure logs for all assigned personnel records'),('Government remittances','Validate assigned remittance schedules before the agreed cutoff'),('HR reporting','Submit reconciled staffing and movement schedules by due date'),('Employee relations support','Record case facts impartially and escalate confidential matters promptly'),('Workforce planning','Review vacancy and succession risks with department heads each quarter'),('Policy governance','Review material HR policy changes against documented requirements'),('HR budget','Explain monthly recruitment and training cost variances exceeding 5%'),('People risk review','Assign actions to all material employee-relations and compliance risks'),('HR team development','Complete quarterly coaching reviews with documented follow-up targets')],
8:[('Incident diagnosis','Record reproducible symptoms and diagnostic evidence for every assigned incident'),('Ticket response','Acknowledge at least 95% of assigned tickets within service targets'),('Service restoration','Restore at least 90% of assigned incidents within agreed resolution targets'),('Endpoint readiness','Complete the standard security checklist before every device handover'),('Patch deployment','Patch at least 95% of assigned eligible devices within the approved window'),('Backup checks','Review assigned backup jobs daily and escalate failures on the same day'),('Access requests','Apply approved access changes within 1 working day and retain authorization'),('Asset inventory','Reconcile assigned serial numbers and custodians monthly'),('Network checks','Document scheduled connectivity and availability checks weekly'),('User guidance','Provide a tested resolution or knowledge article for recurring assigned issues'),('Change records','Document risk, rollback and validation evidence for every assigned change'),('Security escalation','Escalate suspected compromise immediately with preserved evidence'),('Service documentation','Update diagrams and operating instructions within 3 days of approved changes'),('Service capacity','Review capacity trends and corrective actions each quarter'),('Recovery assurance','Complete quarterly restore drills with recorded recovery results'),('Vendor controls','Review critical supplier response and security obligations quarterly'),('Technology roadmap','Prioritize funded system improvements against business risk quarterly'),('Technical coaching','Review diagnostic quality and conduct one team learning session monthly')],
9:[('Campaign assets','Deliver approved campaign assets by the agreed publication date'),('Brand consistency','Meet the brand checklist for every published assigned asset'),('Content schedule','Maintain the assigned four-week content calendar weekly'),('Campaign measurement','Record reach, enquiries and conversion measures for every assigned campaign'),('Enquiry routing','Refer qualified campaign enquiries to the responsible team within 1 day'),('Branch materials','Verify approved versions before dispatching every branch promotion kit'),('Audience research','Document usable audience insights for each assigned campaign brief'),('Social responses','Respond to assigned public enquiries within approved service targets'),('Creative revisions','Complete at least 90% of agreed revisions within 2 working days'),('Consent and permissions','Retain release and usage permissions for every assigned external asset'),('Competitor monitoring','Submit a concise competitor promotion review each month'),('Spend records','Reconcile assigned campaign expenses to approvals monthly'),('Campaign reporting','Deliver an evidence-backed campaign report within 5 days of completion'),('Channel strategy','Review quarterly channel performance against agreed business objectives'),('Marketing budget','Explain every campaign cost variance greater than 5%'),('Reputation risks','Escalate material public complaints with facts on the same working day'),('Partner quality','Evaluate external creative suppliers against documented acceptance criteria'),('Team delivery','Review monthly workloads and remove documented delivery bottlenecks')],
10:[('Executive calendar','Resolve meeting conflicts and confirm the next five working days daily'),('Meeting packs','Distribute complete approved meeting packs at least 2 working days in advance'),('Decision minutes','Circulate accurate draft minutes within 2 working days'),('Action register','Update owners and due dates for all executive actions weekly'),('Correspondence routing','Route priority executive correspondence on the day received'),('Confidential files','Index confidential records and maintain an access log for every release'),('Travel arrangements','Complete approved travel and contingency details before departure'),('Stakeholder coordination','Confirm attendance, agenda and materials for every assigned executive meeting'),('Briefing research','Validate source references for every executive briefing note'),('Expense records','Reconcile executive travel and meeting expenses within 5 working days'),('Document control','Maintain current signed versions of all assigned executive documents'),('Protocol readiness','Complete the approved visitor and event checklist before each engagement'),('Follow-up communication','Send agreed follow-up communications within 1 working day of clearance'),('Board support','Maintain complete board meeting records and action follow-up'),('Information assurance','Review restricted document permissions monthly'),('Office continuity','Maintain and test executive-office continuity contacts quarterly'),('Priority management','Review overdue executive actions weekly with responsible owners'),('Office service review','Review executive-office delivery quality and corrective actions quarterly')],
11:[('Branch service checks','Complete every scheduled assigned branch operations checklist'),('Cash and vault controls','Verify daily cash and vault check evidence for each assigned branch'),('Inventory accountability','Reconcile assigned pledged-item movement records with no unexplained variance'),('Customer turnaround','Meet at least 90% of assigned customer service turnaround targets'),('Transaction accuracy','Achieve at least 98% accuracy in sampled assigned transaction records'),('Opening and closing','Retain complete opening and closing control evidence each operating day'),('Exception escalation','Escalate material cash, security and customer issues on the day detected'),('Staff scheduling','Maintain approved coverage for every assigned service shift'),('Branch documentation','File complete transaction and control records before the next working day'),('Complaint resolution','Record action and update customers within the agreed complaint deadline'),('Field follow-up','Complete assigned branch follow-ups by their approved target dates'),('Operating reports','Submit reconciled branch activity reports by the reporting cutoff'),('Security readiness','Verify assigned alarm, custody and emergency checklists monthly'),('Regional performance','Review monthly branch performance against approved operating targets'),('Service improvement','Implement one measured response to a recurring service issue each quarter'),('Operations budget','Explain monthly controllable cost variances greater than 5%'),('Leadership coverage','Maintain documented relief and succession coverage for key branch duties'),('Risk governance','Review and assign corrective action for every high-risk operations exception')],
12:[('Purchase requisitions','Check specifications, budget and approval for every assigned requisition'),('Supplier quotations','Obtain at least three comparable quotes or document the approved exception'),('Purchase orders','Issue complete approved orders within 2 working days of authorization'),('Delivery tracking','Update outstanding delivery dates and follow up overdue orders weekly'),('Receiving reconciliation','Match receipt, order and invoice before every payment endorsement'),('Supplier records','Maintain current tax, contact and due-diligence documents for assigned suppliers'),('Price comparisons','Document total-cost comparison for every competitive purchase'),('Quality exceptions','Record and escalate every rejected or short delivery within 1 day'),('Procurement files','Index signed purchase records within 2 working days'),('Contract milestones','Track all assigned supplier service milestones and renewal dates'),('Spend reporting','Submit reconciled assigned purchasing schedules by monthly cutoff'),('Requestor updates','Inform requestors of material delivery changes within 1 working day'),('Ethical sourcing','Record conflict checks and preserve competitive selection evidence'),('Sourcing plan','Review quarterly procurement priorities against approved budgets'),('Supplier evaluation','Score critical suppliers against quality, price and delivery each quarter'),('Negotiation outcomes','Record validated savings against a comparable approved baseline'),('Procurement risk','Maintain mitigations for single-source and late-delivery risks'),('Purchasing oversight','Review approval compliance for all material orders monthly')],
}
special={
'Driver I':[('Pre-trip inspection','Complete documented brake, tire, light and fluid checks before every trip'),('Defensive driving','Complete assigned trips without preventable safety incidents'),('Trip authorization','Obtain approved trip purpose, route and passenger manifest before dispatch'),('On-time arrival','Achieve at least 95% arrival within the agreed service window'),('Fuel records','Reconcile fuel receipts and odometer readings for every trip'),('Vehicle cleanliness','Complete daily vehicle cleaning and readiness checks'),('Cargo custody','Obtain signed handover records for every transported item'),('Maintenance reporting','Report defects before dispatch and follow the service schedule'),('Route planning','Select approved routes and communicate material delays immediately'),('Emergency readiness','Maintain checked first-aid and emergency equipment monthly'),('License compliance','Maintain valid driving credentials and required vehicle documents'),('Passenger service','Record and resolve assigned passenger service concerns promptly'),('Trip log accuracy','Submit complete trip and mileage logs by the next working day')],
'Programmer I':[('Requirement clarification','Document acceptance criteria for every assigned development ticket'),('Implementation quality','Complete changes that satisfy all approved acceptance criteria'),('Automated checks','Provide meaningful checks for each modified business rule'),('Code review','Resolve all material review comments before merge'),('Release readiness','Document deployment and rollback steps for every assigned release'),('Defect resolution','Resolve at least 90% of assigned defects within agreed severity targets'),('Secure coding','Close identified high-risk input and access issues before release'),('Database changes','Provide reviewed migration and rollback evidence for each schema change'),('API contracts','Document request, response and error cases for each changed interface'),('Performance care','Meet the agreed response-time target for each assigned critical path'),('Source control','Use traceable commits linked to approved work tickets'),('Technical documentation','Update support instructions within 3 days of production release'),('User acceptance support','Resolve every blocking acceptance issue before sign-off')],
}
cores=[('Positive Attitude','Displays positive attitude at work.'),('Respect','Shows respect to all people in the organization.'),('Accountability','Takes full responsibility of the job including special task or assignment.'),('Commitment','Demonstrates strong commitment to the job.'),('Teamwork','Works cooperatively with others in achieving the goals.'),('Integrity','Exhibits honesty and strong moral uprightness.'),('Continuous Improvement','Provides diligent effort to continuously focus on getting better.'),('Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.')]

def criteria_for(p):
    tasks=special.get(p['title'],domains[p['dept']])
    count=18 if p['rank']<=3 else 13
    tasks=tasks[:count]
    assert len(tasks)==count
    action='Lead and validate' if p['rank']<=3 else 'Check and coordinate' if p['rank']==4 else 'Complete'
    return [(name, f"{p['title']}: {action.lower()} assigned {name.lower()}. {kpi}.") for name,kpi in tasks]

def weights(count):
    # First 12 x 8% + last 4% = 100; heads 17 x 5% + last 15% = 100.
    return [8]*12+[4] if count==13 else [5]*17+[15]

def scores(p,kind,section,count,seed=0):
    # Integer employee ratings, calibrated variation across branch/dept/person/period.
    phase={'Initial':0,'Final':1,'Annual':2,'Q1':1,'Q2':2,'Q3':3}[kind]
    vals=[]
    for i in range(count):
        k=(p['n']*17+i*11+seed*7+phase*3)%19
        value=2 if k<5 else 3 if k<15 else 4
        if kind=='Final' and k<4: value=3
        if kind=='Initial' and k==0: value=2
        if kind=='Annual' or kind.startswith('Q'):
            if p['n']%8==0: value=4 if k<16 else 3
            elif p['n']%7==0: value=2 if k<14 else 3
            elif p['n']%17==0: value=1 if k<7 else 2 if k<16 else 3
            if p['branch']=='Calamba Branch 1' and k%7==0: value=min(4,value+1)
            if p['branch'] in ('Atimonan Branch 1','Lipa Branch 1') and k%11==0: value=max(1,value-1)
        if p['dept'] in (2,8) and k>=10: value=min(4,value+1)
        if kind=='Q3' and p['n']%17 and k<3: value=min(4,value+1)
        vals.append(value)
    return vals

def level(total):
    return 'Outstanding' if total>=3.6 else 'Exceeds Expectations' if total>=2.6 else 'Meets Expectations' if total>=2 else 'Needs Improvement'

def totals(ws,effective,behavior,peers=()):
    kra=round2(sum(round2(w*v/100) for w,v in zip(ws,effective)))
    avgs=[sum(row[i] for row in [behavior]+list(peers))/(1+len(peers)) for i in range(8)]
    core=round2(sum(avgs)/8)
    return dict(kra=kra,core=core,item_averages=avgs,peer_count=len(peers),total=round2(kra*.8+core*.2))

def stamp(day,hour=10,minute=0): return f'{day} {hour:02}:{minute:02}:00'
def deptref(p): return lookup('departments','department_id',department_name=departments[p['dept']])
def jobref(p): return lookup('job_titles','job_title_id',job_title=p['title'],department_id=deptref(p))
def branchref(p): return lookup('branches','branch_id',branch_name=p['branch'])

def demo_signature():
    """Valid PNG containing DEMO ONLY, never an imitation employee signature."""
    import struct, zlib, base64
    glyphs={'D':['11110','10001','10001','10001','10001','10001','11110'],
      'E':['11111','10000','10000','11110','10000','10000','11111'],
      'M':['10001','11011','10101','10101','10001','10001','10001'],
      'O':['01110','10001','10001','10001','10001','10001','01110'],
      'N':['10001','11001','10101','10011','10001','10001','10001'],
      'L':['10000','10000','10000','10000','10000','10000','11111'],
      'Y':['10001','10001','01010','00100','00100','00100','00100'],
      ' ':['00000']*7}
    width,height=240,50
    pixels=[bytearray([255]*width*3) for _ in range(height)]
    for c,ch in enumerate('DEMO ONLY'):
        for y,row in enumerate(glyphs[ch]):
            for x,bit in enumerate(row):
                if bit=='1':
                    for dy in range(3):
                        for dx in range(3):
                            xx,yy=12+c*24+x*3+dx,12+y*3+dy
                            pixels[yy][xx*3:xx*3+3]=bytes((12,55,30))
    def chunk(kind, data): return struct.pack('>I',len(data))+kind+data+struct.pack('>I',zlib.crc32(kind+data)&0xffffffff)
    png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',width,height,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b''.join(b'\0'+bytes(r) for r in pixels)))+chunk(b'IEND',b'')
    return 'data:image/png;base64,'+base64.b64encode(png).decode()

SIGNATURE=demo_signature()
PASSWORD_HASH='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'  # password

def account(p,uid,username,role,issued):
    insert('users',user_id=uid,employee_id=p['id'],username=username,email=username.lower()+'@example.invalid',
      password_hash=PASSWORD_HASH,full_name=p['first']+' '+p['last'],profile_picture=None,
      role=role,branch_id=branchref(p),is_active=1,account_hold=0,first_login_completed=1,must_change_password=0,
      created_at=stamp(issued),updated_at='2026-11-10 08:00:00')

def profile(p):
    n=p['n']; married=n%3==0
    insert('employee_details',employee_id=p['id'],height_m=round2(1.55+(n%24)/100),weight_kg=53+n%28,blood_type=['A+','O+','B+','AB+'][n%4],citizenship='Filipino',created_at=stamp(p['hire']),updated_at='2026-11-10 08:00:00')
    insert('employee_government_ids',employee_id=p['id'],sss_number=f'DEMO-SSS-{n:06}',philhealth_number=f'DEMO-PH-{n:06}',pagibig_number=f'DEMO-PI-{n:06}',tin_number=f'DEMO-TIN-{n:06}')
    city={'Raquel Pawnshop Main Office':'Tayabas City','Pagbilao Branch':'Pagbilao','Calamba Branch 1':'Calamba City','Lucban Branch 1':'Lucban','Lipa Branch 1':'Lipa City','Candelaria Branch 1':'Candelaria','Atimonan Branch 1':'Atimonan'}[p['branch']]
    province='Laguna' if 'Calamba' in city else 'Batangas' if city=='Lipa City' else 'Quezon'
    for atype in ['Residential','Permanent']:
        insert('employee_addresses',employee_id=p['id'],address_type=atype,region='Region IV-A (CALABARZON)',house_no=str(100+n),street='Marikit Demo Street',subdivision='Malinaw Demo Village',barangay='Poblacion',city=city,province=province,zip_code={'Tayabas City':'4327','Pagbilao':'4302','Lucban':'4328','Candelaria':'4323','Atimonan':'4331','Lipa City':'4217','Calamba City':'4027'}[city],created_at=stamp(p['hire']),updated_at='2026-11-10 08:00:00')
    insert('employee_contacts',employee_id=p['id'],telephone_number=f'042000{n:04}',mobile_number=f'090000{n:05}',personal_email=p['code'].lower()+'@example.invalid')
    insert('employee_emergency_contacts',employee_id=p['id'],contact_name=f'Camila {p["last"]}',relationship='Sister',contact_number=f'090001{n:05}',is_primary=1)
    fields=['related_details','admin_offense_details','criminal_charge_details','criminal_conviction_details','separation_details','pwd_details','solo_parent_details','hospital_details','treatment_details']
    insert('employee_disclosures',employee_id=p['id'],**{k:'Not applicable; fictional demo declaration.' for k in fields})
    for typ,first in [('Father','Teodoro'),('Mother','Rosario')]+([('Spouse','Alexis')] if married else []):
        insert('employee_family',employee_id=p['id'],member_type=typ,surname=p['last'],first_name=first,middle_name='Navarro',occupation='Retired teacher' if typ=='Father' else 'Retired bookkeeper' if typ=='Mother' else 'Independent designer')
    if married:
        insert('employee_children',employee_id=p['id'],surname=p['last'],first_name='Andrea',middle_name='Navarro',date_of_birth=date(p['hire'].year+2,7,12))
    insert('employee_siblings',employee_id=p['id'],surname=p['last'],first_name='Camila',middle_name=p['middle'],date_of_birth=date(p['dob'].year+2,4,11))
    degree={1:'BS Real Estate Management',2:'BS Accountancy',3:'BS Business Administration',4:'BS Business Administration',5:'BS Accountancy',6:'BS Industrial Technology',7:'BS Psychology',8:'BS Information Technology',9:'BS Marketing Management',10:'BS Office Administration',11:'BS Business Administration',12:'BS Business Administration'}[p['dept']]
    grad=p['hire'].year-(10 if p['rank']==1 else 6 if p['rank']==3 else 3 if p['rank']==4 else 2)
    for lvl,start,end,course in [('Elementary',grad-16,grad-10,'Completed primary curriculum'),('Secondary',grad-10,grad-6,'Completed secondary curriculum'),('College',grad-4,grad,degree)]:
        insert('employee_education',employee_id=p['id'],education_level=lvl,school_name='Malinaw Demonstration Academy' if lvl!='College' else 'Southern Peninsula Demo College',degree_course=course,period_from=date(start,6,1),period_to=date(end,3,31),highest_level_units='Completed',year_graduated=str(end),honors_received='Merit certificate' if n%5==0 else 'No academic distinction claimed')
    insert('employee_work_experience',employee_id=p['id'],date_from=date(grad,6,1),date_to=p['hire']-timedelta(days=15),job_title='Junior '+p['title'] if p['rank']>=4 else 'Department Coordinator',company_name='Marilag Demonstration Services',monthly_salary=18000+(6-p['rank'])*6500,appointment_status='Permanent',reason_for_leaving='Accepted a role with broader responsibilities; fictional employment history.')
    for ix,topic in enumerate([domains[p['dept']][0][0]+' workshop','Information security and ethical conduct']):
        dt=date(2025,3+ix,10+n%10)
        insert('employee_trainings',employee_id=p['id'],date_from=dt,date_to=dt,training_title=topic,training_type='Technical' if ix==0 else 'Values',no_of_hours=8,conducted_by='Marilag Demo Learning Center',created_at=stamp(dt))
    insert('employee_voluntary_work',employee_id=p['id'],date_from='2025-05-10',date_to='2025-05-11',organization_name='Malinaw Demo Community Circle',organization_address=city+', '+province,no_of_hours=12,position_nature='Volunteer records and logistics assistant',created_at='2025-05-12 09:00:00')
    insert('employee_eligibility',employee_id=p['id'],license_title='Demo driving credential' if p['title']=='Driver I' else 'Internal role competency certificate (fictional)',date_from='2025-02-10',date_to='2027-02-09',license_number=f'DEMO-CERT-{n:05}',date_of_exam='2025-02-05',place_of_exam='Marilag Demo Assessment Center')
    for name in [domains[p['dept']][0][0],'Spreadsheet validation and records control']:
        insert('employee_skills',employee_id=p['id'],skill_name=name)
    insert('employee_recognitions',employee_id=p['id'],recognition_title=f'Demo service milestone: {2025-p["hire"].year} years of documented service')
    insert('employee_memberships',employee_id=p['id'],organization_name='Malinaw Demo Professional Learning Circle')
    insert('employee_personal_properties',employee_id=p['id'],description='Personal laptop for home study; fictional declaration',year_acquired='2023',acquisition_cost=35000+n*100)
    if married and n%2==0:
        insert('employee_real_properties',employee_id=p['id'],description='Small family residence (fictional)',kind='Residential',exact_location=f'{100+n} Marikit Demo Street, {city}',assessed_value=380000,market_value=1400000+n*1000,acquisition_year_mode='2023 - installment purchase',acquisition_cost=1250000)
        insert('employee_liabilities',employee_id=p['id'],nature_of_liability='Housing installment (fictional)',creditor_name='Marilag Demo Housing Cooperative',outstanding_balance=320000+n*100)
    for rix,name in enumerate(['Alicia Maravilla','Noel Santillan','Camille Escalante']):
        insert('employee_references',employee_id=p['id'],reference_name=name,reference_address=f'{200+rix} Masinop Demo Road, {city}',reference_telephone=f'042001{n*3+rix:04}')
    hr=next(x for x in base_hr if x['rank']==3)
    insert('employee_pds_submissions',employee_id=p['id'],submitted_by=p['portal'],status='Approved',hr_notes='Synthetic demo: complete profile checked against the fictional supporting record.',reviewed_by=hr['uid'],reviewed_at='2025-02-20 14:00:00',submitted_at='2025-02-18 09:00:00',created_at='2025-02-18 08:30:00',updated_at='2025-02-20 14:00:00')

templates={}; criterion_counter=950000; score_counter=960000; eval_counter=940000
def template_for(p,typ):
    global criterion_counter
    key=(p['position']['id'],typ)
    if key in templates: return templates[key]
    creator=p['probhead'] if typ in ('Initial','Final') else p['head']
    tid=930000+len(templates)+1
    tasks=criteria_for(p); ws=weights(len(tasks))
    earliest=min(x['hire'] for x in all_people if x['position']['id']==p['position']['id'])
    dt=earliest-timedelta(days=3) if typ in ('Initial','Final') else date(2024,11,1)
    assert creator['reg']<=dt, (p['title'],creator['title'],dt)
    insert('evaluation_templates',template_id=tid,template_name=f'D26 {p["title"]} - {typ}',description=f'Fictional demonstration form for {p["title"]}; measurable role outcomes and the eight standard behaviors.',target_department=departments[p['dept']],target_department_id=deptref(p),target_job_title_id=jobref(p),behavior_framework_code='D26-STANDARD-CORE',behavior_framework_version='1.0',evaluation_type=typ,kra_weight=80,behavior_weight=20,form_code=f'D26-{p["position"]["id"]}-{typ[0]}',revision_date=dt,effective_date_form=dt,status='Active',created_by=creator['uid'],created_at=stamp(dt),updated_at=stamp(dt))
    insert('evaluation_template_positions',template_id=tid,job_title_id=jobref(p))
    criteria=[]
    for i,(name,kpi) in enumerate(tasks):
        criterion_counter+=1
        insert('evaluation_criteria',criterion_id=criterion_counter,template_id=tid,section='KRA',criterion_name=name,description=kpi,kpi_description=kpi,weight=ws[i],scoring_method='Scale_1_4',sort_order=i+1,is_custom=0,created_at=stamp(dt),updated_at=stamp(dt))
        criteria.append(dict(id=criterion_counter,name=name,kpi=kpi,weight=ws[i]))
    behavior=[]
    for i,(name,kpi) in enumerate(cores):
        criterion_counter+=1
        insert('evaluation_criteria',criterion_id=criterion_counter,template_id=tid,section='Behavior',criterion_name=name,description=kpi,kpi_description=kpi,weight=0,scoring_method='Scale_1_4',sort_order=i+1,is_custom=0,created_at=stamp(dt),updated_at=stamp(dt))
        behavior.append(criterion_counter)
    result=dict(id=tid,criteria=criteria,behavior=behavior,weights=ws)
    templates[key]=result
    return result

def write_evaluation(p,typ,kind,start,end,submitted,approved,peer_values=()):
    global eval_counter,score_counter
    eval_counter+=1; eid=eval_counter
    template=template_for(p,typ)
    head=p['probhead'] if typ in ('Initial','Final') else p['head']
    selfkra=scores(p,kind,'KRA',len(template['criteria']))
    effective=selfkra[:]
    # Evidence checks revise selected items, including occasional justified increases.
    for i in range(len(effective)):
        if (i+p['n'])%7==0: effective[i]=max(1,effective[i]-1)
        elif (i+p['n'])%11==0: effective[i]=min(4,effective[i]+1)
    selfbeh=scores(p,kind,'Behavior',8,seed=3)
    reviewedbeh=selfbeh[:] if typ not in ('Initial','Final') else [min(4,max(2,v+(-1 if (i+p['n'])%5==0 else 0))) for i,v in enumerate(selfbeh)]
    result=totals(template['weights'],effective,reviewedbeh,peer_values)
    first_task=template['criteria'][0]['name']; weak=min(range(len(effective)),key=lambda i:effective[i])
    comment=f'Synthetic review for {p["first"]} {p["last"]}: checked the {first_task.lower()} evidence and reconciled {12+p["n"]%19} sampled work records. {template["criteria"][weak]["name"]} needs clearer follow-up; retain source references and review the next four weekly logs.'
    insert('evaluations',evaluation_id=eid,employee_id=p['id'],template_id=template['id'],evaluation_type=typ,evaluation_period_start=start,evaluation_period_end=end,submitted_by=p['portal'],approved_by=head['uid'],supervisor_confirmed_by=head['uid'],supervisor_confirmed_date=stamp(approved,9),supervisor_altered_scores=1,status='Approved',total_score=result['total'],kra_subtotal=result['kra'],behavior_average=result['core'],performance_level=level(result['total']),submitted_date=stamp(submitted,9),approved_date=stamp(approved,10),staff_comments=f'Synthetic self-review: I completed {20+p["n"]%27} assigned work records during this {kind} period; my strongest evidence is in {first_task.lower()}. I will improve {template["criteria"][weak]["name"].lower()} through weekly checks.',employee_consent_agreed=1,employee_signature_data=SIGNATURE,employee_signed_at=stamp(submitted,8,45),supervisor_comments=comment,current_position=p['title'],months_in_position=(end.year-p['hire'].year)*12+end.month-p['hire'].month,desired_position=p['title'],career_growth_suited=0,created_at=stamp(submitted,8),updated_at=stamp(approved,10))
    kra_rows=[]
    for i,c in enumerate(template['criteria']):
        score_counter+=1
        insert('evaluation_scores',score_id=score_counter,evaluation_id=eid,criterion_id=c['id'],score_value=selfkra[i],weighted_score=round2(c['weight']*effective[i]/100),supervisor_override_score=effective[i],supervisor_override_by=head['uid'],supervisor_override_at=stamp(approved,9),created_at=stamp(submitted,9))
        kra_rows.append(dict(score_id=score_counter,criterion_name=c['name'],kpi_description=c['kpi'],weight=c['weight'],self_rating=selfkra[i],rating=effective[i]))
    for i,cid in enumerate(template['behavior']):
        score_counter+=1
        row=dict(score_id=score_counter,evaluation_id=eid,criterion_id=cid,score_value=selfbeh[i],weighted_score=reviewedbeh[i],created_at=stamp(submitted,9))
        if typ in ('Initial','Final'): row.update(supervisor_override_score=reviewedbeh[i],supervisor_override_by=head['uid'],supervisor_override_at=stamp(approved,9))
        insert('evaluation_scores',**row)
    plan=dict(improvement_area=template['criteria'][weak]['name'],support_needed=f'{head["first"]} will review four weekly evidence logs with {p["first"]}; use a checklist and compare exceptions against the documented KPI.',time_frame=f'Four weeks after {approved}',sort_order=1)
    insert('evaluation_dev_plans',evaluation_id=eid,created_at=stamp(approved,9,30),**plan)
    return dict(id=eid,template=template,result=result,self=selfbeh,head=head,rows=kra_rows,comment=comment,plan=plan)

def probation(p):
    records={}
    for typ,months in [('Initial',3),('Final',5)]:
        end=plus_months(p['hire'],months); approved=end+timedelta(days=5); submitted=end+timedelta(days=1)
        ev=write_evaluation(p,typ,typ,p['hire'],end,submitted,approved)
        records[typ]=ev
        head=ev['head']; package=970000+ev['id']-940000
        insert('evaluation_packages',package_id=package,department_id=deptref(p),template_id=ev['template']['id'],evaluation_type=typ,period_start=p['hire'],period_end=end,consolidator_employee_id=head['id'],shared_behavior_score=None,current_step_order=None,status='Approved and Applied',created_at=stamp(submitted,9),updated_at=stamp(approved,10))
        insert('evaluation_package_members',package_id=package,evaluation_id=ev['id'],member_status='Normal',joined_at_step=1)
        label=(('HR Supervisor - Final Review' if p['rank']==5 else 'HR Manager - Final Review' if p['rank']==4 else 'President and CEO - Final Review') if p['dept']==7 else 'President and CEO - Immediate Head Review' if head==ceo else 'Immediate Head - Probationary Review')
        insert('evaluation_package_route_steps',package_id=package,step_order=1,reviewer_employee_id=head['id'],reviewer_user_id=head['uid'],step_label=label,step_type='Review',eligible_role=head['role'] if p['dept']==7 else None,eligible_rank_category_id=None if p['dept']==7 or head==ceo else head['rank'],action_status='Approved',claimed_at=stamp(end+timedelta(days=2),9),acted_at=stamp(approved,10),comments=ev['comment'])
        insert('probationary_evaluation_reviews',evaluation_id=ev['id'],recommendation='Recommend regularization' if typ=='Final' else None,recommendation_reason=f'Synthetic Final decision: {p["first"]} meets the documented role requirements after Initial coaching. The signed evidence checks support regular appointment.' if typ=='Final' else None,immediate_head_user_id=head['uid'],immediate_head_signature_data=SIGNATURE,immediate_head_signed_at=stamp(end+timedelta(days=3),10),employee_acknowledged_by=p['portal'],employee_ack_signature_data=SIGNATURE,employee_acknowledged_at=stamp(approved+timedelta(days=1),9),updated_at=stamp(approved+timedelta(days=1),9))
        for action,dt in [('SUBMITTED',stamp(submitted,9)),('APPROVED',stamp(approved,10)),('LOCKED_AND_APPLIED',stamp(approved,10,1))]:
            insert('evaluation_package_audit',package_id=package,user_id=p['portal'] if action=='SUBMITTED' else head['uid'],action=action,remarks=f'Synthetic demo history: {typ} individual review {action.lower()}; no Board or shared-score stage applies.',created_at=dt)
    final=records['Final']
    if p['dept']==7 and p['rank']==5:
        # The current UI has an approval endpoint but no request-creation path.
        # Keep these explicitly identified as historical compatibility examples.
        hr=next(x for x in base_hr if x['rank']==3)
        insert('regularization_requests',evaluation_id=final['id'],employee_id=p['id'],recommended_by=final['head']['uid'],recommendation_reason='Synthetic historical HR Staff request: Final recommendation and Initial approval are complete.',status='Approved',decided_by=hr['uid'],decided_at=stamp(p['reg'],11),effective_date=p['reg'],decision_reason='Demo historical authorization record; the present Final workflow regularizes directly.',created_at=stamp(p['reg'],10,15))
    insert('employee_edit_history',employee_id=p['id'],edited_by=final['head']['uid'],editor_name=final['head']['first']+' '+final['head']['last'],editor_role=final['head']['role'],step_name='Regularization',change_summary='Synthetic demo history: Probationary to Regular after approved Initial and Final reviews.',changes_json={'employment_status':{'old':'Probationary','new':'Regular'},'regularization_effective_date':{'old':None,'new':str(p['reg'])}},ip_address='127.0.0.1',created_at=stamp(p['reg'],11,15))
    insert('audit_logs',user_id=final['head']['uid'],action_type='UPDATE',entity_type='Employee',entity_id=p['id'],target_employee_id=p['id'],module_name='Employee Management',details='Synthetic demo regularization history; not an approval performed by a live user.',previous_value='Probationary',new_value='Regular',branch_id=branchref(p),department_id=deptref(p),ip_address='127.0.0.1',user_agent='D26 fictional workflow fixture',timestamp=stamp(p['reg'],11,15))
    return records

def cycle(d,kind,start,end,index):
    prefix='annual' if kind=='Annual' else 'quarterly'
    typ='Annual' if kind=='Annual' else 'Quarterly'
    members=groups[d]; owner=owner_people[d]
    cid=974000+index*20+d
    insert(prefix+'_cycle_department_owners',department_id=deptref(owner),owner_position_id=jobref(owner)) if kind in ('Annual','Q1') and d!=7 else None
    insert(prefix+'_evaluation_cycles',cycle_id=cid,department_id=deptref(owner),period_start=start,period_end=end,started_at=stamp(start,8),started_by=owner['uid'],self_rating_deadline=end+timedelta(days=7),first_finalized_at=stamp(end+timedelta(days=8),10),ready_for_approval_at=stamp(end+timedelta(days=9),16),cycle_approved_by=owner['uid'],completed_at=stamp(end+timedelta(days=10),10),first_completed_at=stamp(end+timedelta(days=10),10),created_at=stamp(start,8))
    for pi,p in enumerate(members):
        assert p['reg']<start
        raters=[r for r in members if r['id']!=p['id']]
        if d==10: raters.append(ceo)  # Same-department immediate head is eligible before self-rating.
        peers=[scores(p,kind,'Behavior',8,seed=r['n']) for r in raters]
        approved=end+timedelta(days=8)
        ev=write_evaluation(p,typ,kind,start,end,end+timedelta(days=2),approved,peers)
        mid=976000+ev['id']-940000
        insert(prefix+'_evaluation_members',member_id=mid,cycle_id=cid,employee_id=p['id'],evaluation_id=ev['id'],position_id=jobref(p),reviewer_position_id=jobref(p['head']),position_name=p['title'],self_values_json=ev['self'],current_version=1,revision_pending=0,finalized_at=stamp(approved,10),is_late_participant=0)
        peer_rows=[]
        for rix,(r,values) in enumerate(zip(raters,peers)):
            dt=stamp(approved,9) if r==p['head'] else stamp(end+timedelta(days=5+(rix%2)),10+(rix%5),rix%50)
            insert(prefix+'_peer_submissions',cycle_id=cid,rater_employee_id=r['id'],target_employee_id=p['id'],submitted_by=r['uid'],scores_json=values,rater_signature_data=SIGNATURE if r==p['head'] else None,rater_signed_at=dt if r==p['head'] else None,is_late=0,submitted_at=dt)
            peer_rows.append(dict(scores_json=json.dumps(values,separators=(',',':')),submitted_at=dt,is_late=0))
            insert('audit_logs',user_id=r['uid'],action_type='SUBMIT',entity_type=typ+' Evaluation',entity_id=ev['id'],target_employee_id=p['id'],module_name='Performance & Evaluation',details=f'Synthetic peer feedback from {r["first"]}: observed {domains[d][(rix+p["n"])%13][0].lower()} during {kind}; recorded behavior ratings from documented teamwork and follow-up examples.',branch_id=branchref(p),department_id=deptref(p),ip_address='127.0.0.1',user_agent='D26 fictional workflow fixture',timestamp=dt)
        external_head=ev['head']['dept']!=p['dept']
        insert(prefix+'_kra_approvals',member_id=mid,approved_by=ev['head']['uid'],approved_at=stamp(approved,9),remarks=ev['comment'],ratings_json=ev['rows'],rater_signature_data=SIGNATURE if external_head else None,rater_signed_at=stamp(approved,9) if external_head else None)
        snapshot=ev['result']|dict(score_complete=True,expected_peers=len(members)-1,previous_score=None,kra_rows=ev['rows'],self_values=ev['self'],peers=peer_rows,head_comments=ev['comment'],development_plans=[{k:ev['plan'][k] for k in ('improvement_area','support_needed','time_frame')}])
        insert(prefix+'_evaluation_versions',member_id=mid,version_number=1,finalized_by=ev['head']['uid'],finalized_at=stamp(approved,10),remarks='Synthetic complete result: immediate-head KRA approval after all department 360 submissions.',snapshot_json=snapshot)
        if kind=='Q3':
            insert('notifications',user_id=p['portal'],title='Demo Quarterly score complete',message=f'Fictional demo: {p["first"]}, your Q3 score is {ev["result"]["total"]:.2f}/4.00 after KRA review and all required behavior ratings. The department owner approved this cycle.',link=f'/Raquel_HRD/employee/evaluation-history-view.php?evaluation_id={ev["id"]}',category='evaluations',needs_action=0,is_read=1,created_at=stamp(end+timedelta(days=10),11))
    insert('audit_logs',user_id=owner['uid'],action_type='APPROVE',entity_type=typ+' Cycle',entity_id=cid,module_name='Performance & Evaluation',details=f'Synthetic demo history: {kind} cycle approved after all {len(members)} participant results were complete.',department_id=deptref(owner),ip_address='127.0.0.1',user_agent='D26 fictional workflow fixture',timestamp=stamp(end+timedelta(days=10),10))

def build_sql():
    emit('''-- D26 fictional employee and complete evaluation-workflow demonstration.
-- Reference date: 10 November 2026. NO completed 2026 Annual cycle is fabricated.
-- PREREQUISITES / EXACT ORDER (MariaDB 10.4+, MySQL client with routine privilege):
-- 1. Select a NEW disposable database named raquel_demo_<your_suffix>.
-- 2. Import COPIES of database/1st_schema_tables.sql, 2nd_seed_organization.sql,
--    and 3rd_seed_HR_accounts_.sql in that order, replacing their hardcoded
--    raquel_hris database token with that disposable name in the COPIES ONLY.
--    The original schema DROPS raquel_hris: do not execute it unmodified for this demo.
-- 3. Import THIS file into that selected disposable database. Do not import other fixtures.
-- 4. Run 02_verify_demo.sql and validate_demo.php in this folder.
-- The included validate_demo.py performs these steps against an isolated database only.
-- This file contains no USE, DROP DATABASE, DROP TABLE, REPLACE, or FK-disable statement.
-- Fresh-only guard, exact base-reference checks and transactional DML reject collisions.
-- The runtime-compatible annual/quarterly table DDL is copied from current app SQL.
-- DDL precedes START TRANSACTION because MariaDB implicitly commits DDL.
-- IDs 910000..999999 and codes/usernames D26-* are reserved for this demo.
-- Original admin/HR employees and four base accounts are never updated or replaced.
-- All names, contacts, IDs, signatures, approvals, comments and histories are fictional.
-- PNG signature fields visibly read DEMO ONLY; they are not actual consent/signatures.
-- The CEO is reviewer-only: current code has no supported reviewer for the CEO.
-- The two regularization_requests rows are explicitly historical compatibility examples;
-- current checkout has decision endpoints but no request-creation endpoint.
-- Password for added demo accounts: password. Existing base passwords stay untouched.
SET NAMES utf8mb4;
SET time_zone='+08:00';
SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET FOREIGN_KEY_CHECKS=1;
DELIMITER $$
CREATE PROCEDURE d26_load_fictional_workflow()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF DATABASE() IS NULL OR DATABASE() NOT REGEXP '^raquel_demo_[A-Za-z0-9_]+$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='D26 requires a selected disposable raquel_demo_<suffix> database; never raquel_hris';
  END IF;
  IF (SELECT COUNT(*) FROM employees)<>3 OR (SELECT COUNT(*) FROM users)<>4
     OR (SELECT COUNT(*) FROM evaluation_templates)<>0 OR (SELECT COUNT(*) FROM evaluations)<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='D26 requires a fresh database with ONLY the documented three base seeds; rerun/collision refused';
  END IF;
  IF (SELECT COUNT(*) FROM departments WHERE is_active=1 AND deleted_at IS NULL)<>12
     OR (SELECT COUNT(*) FROM users WHERE (username='elena.delgado' AND employee_id=101 AND role='HR Manager') OR (username='patricia.gomez' AND employee_id=301 AND role='HR Supervisor') OR (username='miguel.torres' AND employee_id=302 AND role='HR Staff') OR (username='admin' AND employee_id IS NULL))<>4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='D26 base organization or HR account references do not match current seeds';
  END IF;''')
    for p in people:
        pos=p['position']
        parent=next((j for j in positions.values() if j['id']==pos['parent']),None)
        parent_ref=lookup('job_titles','job_title_id',job_title=parent['title'],department_id=lookup('departments','department_id',department_name=departments[parent['dept']])) if parent else None
        emit(f"  IF (SELECT COUNT(*) FROM job_titles j JOIN departments d ON d.department_id=j.department_id WHERE j.job_title={sql(p['title'])} AND d.department_name={sql(departments[p['dept']])} AND j.rank_category_id={p['rank']} AND j.is_active=1 AND (j.reports_to <=> {sql(parent_ref)}))<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT={sql('Missing/ambiguous job or head reference: '+p['title'])}; END IF;")
    emit("  IF (SELECT COUNT(*) FROM employees e JOIN job_titles j ON j.job_title_id=e.job_title_id JOIN departments d ON d.department_id=e.department_id WHERE d.department_name='Human Resources' AND ((e.employee_id=101 AND e.employee_code='HRD-001' AND j.job_title='HR Manager I') OR (e.employee_id=301 AND e.employee_code='HRD-002' AND j.job_title='HR Supervisor I') OR (e.employee_id=302 AND e.employee_code='HRD-003' AND j.job_title='HR Staff II')))<>3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Base HR employee position references differ from the documented seed'; END IF;")
    for b in sorted({p['branch'] for p in people}):
        emit(f"  IF (SELECT COUNT(*) FROM branches WHERE branch_name={sql(b)} AND is_active=1 AND deleted_at IS NULL)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT={sql('Missing/ambiguous branch: '+b)}; END IF;")
    for typ in ('annual','quarterly'):
        emit((ROOT/f'database/zLAST_{typ}_peer_evaluations.sql').read_text(encoding='utf-8-sig'))
    emit('''ALTER TABLE evaluation_package_route_steps
    ADD COLUMN IF NOT EXISTS eligible_role VARCHAR(50) NULL AFTER step_type,
    ADD COLUMN IF NOT EXISTS eligible_rank_category_id INT NULL AFTER eligible_role,
    ADD COLUMN IF NOT EXISTS claimed_at DATETIME NULL AFTER action_status;
ALTER TABLE evaluation_scores
    ADD COLUMN IF NOT EXISTS dept_manager_override_score DECIMAL(5,2) NULL,
    ADD COLUMN IF NOT EXISTS dept_manager_override_by INT NULL,
    ADD COLUMN IF NOT EXISTS dept_manager_override_at DATETIME NULL;
START TRANSACTION;''')
    for p in people:
        insert('employees',employee_id=p['id'],employee_code=p['code'],first_name=p['first'],last_name=p['last'],middle_name=p['middle'],date_of_birth=p['dob'],place_of_birth='Tayabas City, Quezon',gender=p['gender'],civil_status='Married' if p['n']%3==0 else 'Single',hire_date=p['hire'],job_title=p['title'],job_title_id=jobref(p),department_id=deptref(p),rank_category_id=lookup('job_titles','rank_category_id',job_title=p['title'],department_id=deptref(p)),branch_id=branchref(p),reports_to=p['head']['id'] if p['head'] else None,employment_status='Regular',regularization_effective_date=p['reg'] if p!=ceo else None,employment_type='Full-time',profile_picture=None,is_active=1,created_at=stamp(p['hire']),updated_at='2026-11-10 08:00:00')
    for p in all_people:
        account(p,p['portal'],p['code'] if not p['base'] else 'D26-REF-'+p['code'],'President and CEO' if p==ceo else 'Employee',p['hire'])
        if p['uid']!=p['portal']: account(p,p['uid'],p['code']+'.hris' if not p['base'] else 'D26-REF-'+p['code']+'.hris',p['role'],p['reg'])
    for p in people: profile(p)
    histories={}
    for p in people:
        if p==ceo: continue
        histories[p['code']]=probation(p)
        final=histories[p['code']]['Final']['id']
        emit(f'UPDATE employees SET probationary_eligible_evaluation_id={final},probationary_eligible_at={sql(stamp(p["reg"],10))},probationary_regularization_eligible=0,probationary_next_evaluation_type=NULL,probationary_next_evaluation_due_date=NULL WHERE employee_id={p["id"]};')
    for index,(kind,start,end) in enumerate([('Annual',date(2025,1,1),date(2025,12,31)),('Q1',date(2026,1,1),date(2026,3,31)),('Q2',date(2026,4,1),date(2026,6,30)),('Q3',date(2026,7,1),date(2026,9,30))]):
        for d in departments: cycle(d,kind,start,end,index)
    emit('''COMMIT;
SELECT 'D26 import complete; all records are fictional' AS result;
END$$
DELIMITER ;
CALL d26_load_fictional_workflow();
DROP PROCEDURE d26_load_fictional_workflow;''')
    (HERE/'01_fictional_workflow_demo.sql').write_text('\n'.join(out)+'\n',encoding='utf-8')
    manifest=dict(as_of=str(AS_OF),new_employees=len(people),complete_progression=len(people)-1,base_hr_preserved=3,periodic_participants=sum(map(len,groups.values())),templates=len(templates),criteria=criterion_counter-950000,evaluations=eval_counter-940000,scores=score_counter-960000,departments=12,cycles=48,it_story=next({k:str(p[k]) for k in ('code','id','first','last','hire','reg','title')} for p in people if p['title']=='Technical Support Staff I'),accounts=[dict(code=p['code'],employee_id=p['id'],user_id=p['portal'],hris_user_id=p['uid'],name=p['first']+' '+p['last'],position=p['title'],department=departments[p['dept']],hire=str(p['hire']),regularization=None if p==ceo else str(p['reg'])) for p in all_people])
    (HERE/'manifest.json').write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
    print(json.dumps({k:v for k,v in manifest.items() if k!='accounts'},indent=2))

def build():
    # Implemented below in the next section to keep the domain content readable.
    build_sql()

if __name__=='__main__':
    build()
