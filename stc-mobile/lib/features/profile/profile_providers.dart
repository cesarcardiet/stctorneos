import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/dio_client.dart';
import 'data/account_portal_repository.dart';

final accountPortalRepositoryProvider = Provider<AccountPortalRepository>((ref) {
  return AccountPortalRepository(ref.watch(dioClientProvider));
});
