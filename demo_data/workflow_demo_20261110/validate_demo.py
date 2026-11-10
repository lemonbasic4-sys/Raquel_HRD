"""Import ONLY into a new isolated database; never reads config/database.php."""
from pathlib import Path
import subprocess
import datetime
import json
import hashlib
import re

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
MYSQL=Path(r'C:\xampp\mysql\bin\mysql.exe')
PHP=Path(r'C:\xampp\php\php.exe')
DB='raquel_demo_validation_20261110_'+datetime.datetime.now().strftime('%H%M%S')
assert re.fullmatch(r'raquel_demo_validation_20261110_\d{6}',DB)

def run_sql(content,db=None):
    args=[str(MYSQL),'--default-character-set=utf8mb4','-u','root','--batch','--raw']
    if db: args.append(db)
    proc=subprocess.run(args,input=content.encode('utf-8'),capture_output=True)
    if proc.returncode: raise RuntimeError(proc.stderr.decode('utf-8',errors='replace')+'\n'+proc.stdout.decode('utf-8',errors='replace')[-1800:])
    return proc.stdout.decode('utf-8').replace('\r\n','\n')

if __name__=='__main__':
    exists=run_sql(f"SELECT COUNT(*) AS n FROM information_schema.schemata WHERE schema_name='{DB}';")
    if exists.splitlines()[-1]!='0': raise RuntimeError('Refusing to reuse an existing validation database')
    snapshot=HERE/'validation'
    snapshot.mkdir(exist_ok=True)
    (snapshot/'database_name.txt').write_text(DB+'\n',encoding='utf-8')
    source_paths=[ROOT/'database'/x for x in ['1st_schema_tables.sql','2nd_seed_organization.sql','3rd_seed_HR_accounts_.sql']]
    hashes={str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in source_paths}
    log=[]
    for path in source_paths:
        source=path.read_text(encoding='utf-8-sig')
        # Original schema hardcodes DROP/CREATE/USE raquel_hris. Transform only
        # this in-memory validation copy; never execute the original source.
        isolated=source.replace('raquel_hris',DB)
        assert 'raquel_hris' not in isolated
        log.append(path.name+':\n'+run_sql(isolated,DB if path!=source_paths[0] else None))
    before=run_sql('SELECT * FROM users ORDER BY user_id; SELECT * FROM employees ORDER BY employee_id;',DB)
    (snapshot/'base_rows_before.tsv').write_text(before,encoding='utf-8')
    log.append('01_fictional_workflow_demo.sql:\n'+run_sql((HERE/'01_fictional_workflow_demo.sql').read_text(encoding='utf-8'),DB))
    after=run_sql('SELECT * FROM users WHERE user_id IN (1,2,3,4) ORDER BY user_id; SELECT * FROM employees WHERE employee_id IN (101,301,302) ORDER BY employee_id;',DB)
    if before!=after: raise RuntimeError('Base employee/account rows were modified')
    (snapshot/'base_rows_after.tsv').write_text(after,encoding='utf-8')
    output=run_sql((HERE/'02_verify_demo.sql').read_text(encoding='utf-8'),DB)
    (snapshot/'verification_results.tsv').write_text(output,encoding='utf-8')
    phprun=subprocess.run([str(PHP),str(HERE/'validate_demo.php'),DB],cwd=ROOT,capture_output=True)
    (snapshot/'php_workflow_results.txt').write_bytes(phprun.stdout+phprun.stderr)
    if phprun.returncode: raise RuntimeError((phprun.stdout+phprun.stderr).decode('utf-8',errors='replace'))
    final_base=run_sql('SELECT * FROM users WHERE user_id IN (1,2,3,4) ORDER BY user_id; SELECT * FROM employees WHERE employee_id IN (101,301,302) ORDER BY employee_id;',DB)
    if before!=final_base: raise RuntimeError('PHP reader validation changed base employee/account rows')
    for path in source_paths:
        if hashlib.sha256(path.read_bytes()).hexdigest()!=hashes[str(path.relative_to(ROOT))]: raise RuntimeError('Source seed changed')
    # A rerun must fail before changing any demo rows.
    retry=subprocess.run([str(MYSQL),'--default-character-set=utf8mb4','-u','root',DB],input=(HERE/'01_fictional_workflow_demo.sql').read_bytes(),capture_output=True)
    if retry.returncode==0 or b'fresh database' not in retry.stderr: raise RuntimeError('Collision guard did not reject rerun')
    final_counts=run_sql('SELECT (SELECT COUNT(*) FROM employees) AS employees,(SELECT COUNT(*) FROM evaluations) AS evaluations;',DB)
    if final_counts.splitlines()[-1]!='58\t336': raise RuntimeError('Failed rerun changed fixture counts')
    # The failed CALL can leave the routine defined in this disposable database;
    # remove that routine only, so the retained validation database stays tidy.
    run_sql('DROP PROCEDURE IF EXISTS d26_load_fictional_workflow;',DB)
    log.append('Rerun/collision guard: correctly rejected. Base rows byte-for-byte preserved.')
    (snapshot/'import_log.txt').write_text('\n'.join(log),encoding='utf-8')
    (snapshot/'source_hashes.json').write_text(json.dumps(hashes,indent=2)+'\n',encoding='utf-8')
    print(DB)
    print(phprun.stdout.decode('utf-8'))

