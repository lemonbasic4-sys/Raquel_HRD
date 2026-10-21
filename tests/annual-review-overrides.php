<?php
require_once __DIR__ . '/../includes/annual-evaluations.php';

function assertOverrides(bool $valid, string $message): void {
    if (!$valid) throw new RuntimeException($message);
}
$defaults=[11=>4.0,12=>3.0,13=>3.5];
assertOverrides(annualResolveReviewOverrides($defaults,[])===$defaults,'Missing overrides must retain all original ratings.');
assertOverrides(annualResolveReviewOverrides($defaults,[11=>'',12=>'',13=>''])===$defaults,'Blank overrides must retain all original ratings.');
assertOverrides(annualResolveReviewOverrides($defaults,[12=>'3.25'])===[11=>4.0,12=>3.25,13=>3.5],'Only the entered decimal override should change.');
foreach (['0','4.01','2.555','invalid',[]] as $invalid) {
    try { annualResolveReviewOverrides($defaults,[12=>$invalid]); }
    catch (DomainException $error) { continue; }
    throw new RuntimeException('Invalid override was accepted.');
}
try { annualResolveReviewOverrides($defaults,[99=>'3']); throw new RuntimeException('Unknown item accepted.'); }
catch (DomainException $error) {}
$self=[4.0,3.0,4.0,4.0,4.0,4.0,4.0,4.0];
assertOverrides(annualValidateScores(annualResolveReviewOverrides($self,[1=>'3.25']))===[4.0,3.25,4.0,4.0,4.0,4.0,4.0,4.0],'Behavior defaults must resolve into eight valid scores.');
echo "Annual review override checks passed.\n";
