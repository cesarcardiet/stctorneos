import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:stc_mobile/core/network/dio_client.dart';
import 'package:stc_mobile/core/storage/token_store.dart';
import 'package:stc_mobile/features/auth/data/auth_repository.dart';

class _MockDioClient extends Mock implements DioClient {}

class _MockTokenStore extends Mock implements TokenStore {}

void main() {
  late _MockDioClient client;
  late _MockTokenStore tokenStore;
  late AuthRepository repository;

  setUp(() {
    client = _MockDioClient();
    tokenStore = _MockTokenStore();
    repository = AuthRepository(client: client, tokenStore: tokenStore);
  });

  test('login stores token on success', () async {
    when(() => client.postJson('/auth/login', data: any(named: 'data')))
        .thenAnswer((_) async => {
              'ok': true,
              'data': {
                'token': 'abc123',
                'user': {
                  'id': 1,
                  'name': 'Demo',
                  'email': 'demo@test.com',
                  'status': 'active',
                  'role': 'Delegado',
                  'role_slug': 'delegado',
                  'scope': 'Club',
                  'roles': [],
                  'permissions': [],
                  'navigation': [],
                  'tournaments': [],
                  'delegations': [],
                  'context': {},
                },
              },
            });
    when(() => tokenStore.save(any())).thenAnswer((_) async {});

    final session = await repository.login(email: 'demo@test.com', password: 'secret');

    expect(session.token, 'abc123');
    verify(() => tokenStore.save('abc123')).called(1);
  });
}
