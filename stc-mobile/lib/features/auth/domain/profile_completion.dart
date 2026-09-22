import 'user_profile.dart';

const _preconfiguredRoles = {
  'delegado',
  'admin_torneo',
  'super_admin',
  'arbitro',
  'mesa',
  'coordinador',
  'tutor',
  'jugador',
  'tutor_ficha',
  'staff',
};

bool profileNeedsCompletion(UserProfile user) {
  if (user.status != 'active') return true;
  if (user.name.trim().length < 2) return true;

  final slug = user.roleSlug ?? '';
  if (_preconfiguredRoles.contains(slug)) return false;

  // Espectadores / cuentas nuevas: pedir teléfono solo si falta.
  if (slug == 'consulta') {
    return (user.phone ?? '').trim().isEmpty;
  }

  return false;
}
