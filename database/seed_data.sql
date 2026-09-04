-- Safe starter data for Catbalogan AI Assistant.
-- This file intentionally contains no user accounts or real credentials.
-- Verify all government details with the relevant Catbalogan office before use.

SET NAMES utf8mb4;

INSERT INTO permits
    (code, name, office, description, requirements, steps, fees, processing_time, validity, address, contact, verified_at)
VALUES
(
    'barangay_clearance',
    'Barangay Clearance',
    'Barangay Office',
    'A barangay clearance is commonly requested for employment, school, business, and other local transactions. Requirements and fees may vary by barangay.',
    'Valid government-issued ID
Proof of residency or barangay record
Purpose of the clearance',
    'Visit your barangay office.
Submit the required identification and state the purpose.
Pay the applicable fee.
Claim the clearance when released.',
    'Confirm the current amount with your barangay office.',
    'Usually released during the same office day when documents are complete.',
    'Validity depends on the requesting institution or transaction.',
    'Your barangay hall in Catbalogan City',
    'Contact your barangay office for the current schedule and requirements.',
    NULL
),
(
    'new_business_permit',
    'New Business Permit',
    'Business Permits and Licensing Office (BPLO)',
    'A new business permit is required before operating a new business. The exact documentary requirements depend on the business activity, location, and registration status.',
    'DTI or SEC registration, as applicable
Barangay clearance
Proof of business address or lease document
Valid government-issued ID
Completed application forms
Other clearances required for the business type',
    'Secure the application forms and confirm the documentary requirements.
Submit the application to the appropriate city office.
Complete assessment and payment.
Claim the approved permit after release.',
    'Fees depend on the business classification and applicable local taxes. Confirm the assessment with BPLO.',
    'Varies depending on document completeness and required clearances.',
    'Usually valid for the applicable business permit period; confirm the current rules with BPLO.',
    'Catbalogan City Business Permits and Licensing Office',
    'Contact Catbalogan City BPLO for current office hours, fees, and requirements.',
    NULL
),
(
    'business_permit_renewal',
    'Business Permit Renewal',
    'Business Permits and Licensing Office (BPLO)',
    'Business permit renewal keeps an existing business compliant for the new permit period. Renewal requirements and deadlines should be confirmed with BPLO.',
    'Previous business permit
Barangay clearance, if required
Updated business registration or supporting records, if applicable
Valid government-issued ID
Completed renewal forms
Proof of payment of assessed fees',
    'Request or download the renewal forms.
Submit the previous permit and supporting documents.
Wait for assessment.
Pay the assessed amount and claim the renewed permit.',
    'Fees depend on the business classification and assessment. Confirm the current amount with BPLO.',
    'Varies depending on document completeness and assessment.',
    'Valid for the applicable renewal period; confirm the current rules with BPLO.',
    'Catbalogan City Business Permits and Licensing Office',
    'Contact Catbalogan City BPLO for the current renewal deadline and requirements.',
    NULL
);

INSERT INTO kb_entries (permit_id, intent, keywords, answer, priority)
SELECT id, 'overview',
       CASE code
           WHEN 'barangay_clearance' THEN 'barangay clearance, barangay certificate, clearance from barangay, barangay'
           WHEN 'new_business_permit' THEN 'new business permit, business permit, start business, opening business, register business, bagong negosyo'
           WHEN 'business_permit_renewal' THEN 'business permit renewal, renew business permit, renewal, existing business, update business permit'
       END,
       description,
       2
FROM permits
WHERE code IN ('barangay_clearance', 'new_business_permit', 'business_permit_renewal');

INSERT INTO kb_entries (permit_id, intent, keywords, answer, priority)
VALUES
(
    NULL,
    'greeting',
    'hello, hi, good morning, good afternoon, good evening, kumusta, kamusta',
    'Hello! I can help you find information about Catbalogan permits and clearances, including requirements, steps, fees, and processing time.',
    3
),
(
    NULL,
    'thanks',
    'thank you, thanks, salamat, maraming salamat',
    'You are welcome! Let me know if you need information about another permit or clearance.',
    2
),
(
    NULL,
    'goodbye',
    'bye, goodbye, see you, paalam',
    'Goodbye! Please verify current requirements and fees with the responsible city or barangay office.',
    2
),
(
    NULL,
    'help_menu',
    'help, what can you ask, what do you know, options, tulong',
    'I can provide general information about Barangay Clearance, New Business Permit, and Business Permit Renewal. Ask about requirements, steps, fees, deadlines, processing time, or where to apply.',
    2
),
(
    NULL,
    'office_hours',
    'office hours, opening hours, schedule, oras, bukas ba',
    'Office schedules can change. Please contact the responsible Catbalogan city or barangay office to confirm its current hours before visiting.',
    2
),
(
    NULL,
    'fallback',
    'i do not understand, unclear, none, other',
    'I am not sure I understood that. Try asking about Barangay Clearance, New Business Permit, or Business Permit Renewal, or ask about requirements, steps, fees, or processing time.',
    1
);
