/** Age in full years as of `at` (defaults to now) — used to help staff pick the right age group. */
export function calculateAge(birthDate: Date, at: Date = new Date()): number {
  let age = at.getFullYear() - birthDate.getFullYear();
  const hasNotHadBirthdayYet =
    at.getMonth() < birthDate.getMonth() ||
    (at.getMonth() === birthDate.getMonth() && at.getDate() < birthDate.getDate());
  if (hasNotHadBirthdayYet) age -= 1;
  return age;
}
