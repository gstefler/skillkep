# ADR-0007: Course access – company availability plus explicit enrolment

- Status: Accepted
- Date: 2026-11-20

## Context

Courses are uploaded once and sold to several companies. A company buying a course does not mean every employee should take it: the company decides who does. Access must therefore be decided per learner, but an admin must never be able to hand out a course their company has not got.

## Decision

- Superadmins upload courses (resumable chunked ZIP upload, queued import), publish them, and make them **available** to companies (`company_course`).
- Availability enrols nobody. Every learner is **enrolled one by one** (`course_user`), by a superadmin or by the admin of the learner's company, and only for courses available to that company.
- A learner can open a course only if all three hold: enrolled, the course is published, and it is still available to the learner's company (`CoursePolicy::view`).
- Removing a company from a course also removes its learners' enrolments. Their attempts and scores are kept.
- Admins see and enrol only within their own company; saving the learner list never touches other companies' enrolments.

## Consequences

- No "enrol everybody" side effect when a company gets a new course; admins choose deliberately (the UI offers select-all per company).
- Enrolment rules live in one place (`Course::enrollableLearnersFor`, `CourseAccessController`), so future deadlines or mandatory flags can hang off `course_user`.
- If a learner moves to another company, their enrolments stay in the table but stop granting access until the new company has the course.
