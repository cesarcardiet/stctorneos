import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/dio_client.dart';
import 'data/tutor_ficha_repository.dart';

final tutorFichaRepositoryProvider = Provider<TutorFichaRepository>((ref) {
  return TutorFichaRepository(ref.watch(dioClientProvider));
});
