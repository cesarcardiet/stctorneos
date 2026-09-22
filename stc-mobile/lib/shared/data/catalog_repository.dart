import '../../core/network/dio_client.dart';
import '../models/api_response.dart';

class CatalogRepository {
  CatalogRepository(this._client);

  final DioClient _client;

  Future<Map<String, dynamic>> fetchHome() async {
    final json = await _client.getJson('/home');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<List<Map<String, dynamic>>> fetchTournaments() async {
    final json = await _client.getJson('/tournaments');
    final response = ApiResponse<List<dynamic>>.fromJson(json, (data) => data as List);
    return (response.data ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> fetchTournament(int id) async {
    final json = await _client.getJson('/tournaments/$id');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchStandings(int categoryId) async {
    final json = await _client.getJson('/categories/$categoryId/standings');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchRankings(int categoryId) async {
    final json = await _client.getJson('/categories/$categoryId/rankings');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchPlayer(int id) async {
    final json = await _client.getJson('/players/$id');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchCategory(int id) async {
    final json = await _client.getJson('/categories/$id');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchMatch(int id) async {
    final json = await _client.getJson('/matches/$id');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<List<Map<String, dynamic>>> fetchMatches({int? tournamentId, String? status}) async {
    final query = <String, dynamic>{};
    if (tournamentId != null) query['tournament_id'] = tournamentId;
    if (status != null) query['status'] = status;

    final json = await _client.getJson('/matches', query: query.isEmpty ? null : query);
    final response = ApiResponse<List<dynamic>>.fromJson(json, (data) => data as List);
    return (response.data ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, int>> fetchTournamentStats(int tournamentId) async {
    final tournament = await fetchTournament(tournamentId);
    final categories = (tournament['categories'] as List<dynamic>? ?? []);
    var teams = 0;
    var players = 0;

    await Future.wait(categories.map((category) async {
      final id = (category as Map)['id'] as int;
      try {
        final detail = await fetchCategory(id);
        final categoryTeams = (detail['teams'] as List<dynamic>? ?? []);
        teams += categoryTeams.length;
        players += categoryTeams.length * 12;
      } catch (_) {}
    }));

    final matches = await fetchMatches(tournamentId: tournamentId);
    final venues = matches
        .map((m) => m['field'] as String?)
        .whereType<String>()
        .where((f) => f.isNotEmpty)
        .toSet()
        .length;
    final live = matches.where((m) => m['status'] == 'live').length;

    if (players == 0 && teams > 0) players = teams * 12;

    return {
      'teams': teams,
      'matches': matches.length,
      'players': players,
      'venues': venues,
      'live': live,
    };
  }

  Future<Map<String, dynamic>> fetchTeam(int id) async {
    final json = await _client.getJson('/teams/$id');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>?> fetchTeamStandingRow(
    int teamId,
    String? categoryName, {
    int? tournamentId,
  }) async {
    if (categoryName == null || categoryName.isEmpty) return null;

    final tournaments = tournamentId != null
        ? [{'id': tournamentId}]
        : await fetchTournaments();

    for (final tournament in tournaments) {
      final detail = await fetchTournament(tournament['id'] as int);
      for (final category in (detail['categories'] as List<dynamic>? ?? [])) {
        final cat = category as Map<String, dynamic>;
        if (cat['name'] != categoryName) continue;

        final standings = await fetchStandings(cat['id'] as int);
        for (final group in (standings['groups'] as List<dynamic>? ?? [])) {
          for (final row in ((group as Map)['rows'] as List<dynamic>? ?? [])) {
            final map = Map<String, dynamic>.from(row as Map);
            final team = map['team'] as Map<String, dynamic>?;
            if (team?['id'] == teamId) return map;
          }
        }
      }
    }
    return null;
  }

  Future<Map<String, dynamic>?> fetchNextMatchForTeam(int teamId, {int? tournamentId}) async {
    final matches = await fetchMatches(tournamentId: tournamentId);
    final now = DateTime.now();

    Map<String, dynamic>? best;
    DateTime? bestDate;

    for (final match in matches) {
      if (match['status'] == 'finished' || match['status'] == 'validated') continue;
      final home = match['home'] as Map<String, dynamic>?;
      final away = match['away'] as Map<String, dynamic>?;
      if (home?['id'] != teamId && away?['id'] != teamId) continue;

      final scheduled = match['scheduled_at'] as String?;
      final dt = scheduled != null ? DateTime.tryParse(scheduled) : null;
      if (dt == null) continue;
      if (dt.isBefore(now.subtract(const Duration(hours: 3))) && match['status'] != 'live') continue;

      if (bestDate == null || dt.isBefore(bestDate)) {
        best = match;
        bestDate = dt;
      }
    }
    return best;
  }

  Future<List<String>> fetchTeamForm(int teamId, {int? tournamentId, int limit = 3}) async {
    final matches = await fetchMatches(tournamentId: tournamentId);
    final finished = matches.where((m) {
      final status = m['status'] as String?;
      if (status != 'finished' && status != 'validated') return false;
      final home = m['home'] as Map<String, dynamic>?;
      final away = m['away'] as Map<String, dynamic>?;
      return home?['id'] == teamId || away?['id'] == teamId;
    }).toList();

    finished.sort((a, b) {
      final da = DateTime.tryParse(a['scheduled_at'] as String? ?? '') ?? DateTime.fromMillisecondsSinceEpoch(0);
      final db = DateTime.tryParse(b['scheduled_at'] as String? ?? '') ?? DateTime.fromMillisecondsSinceEpoch(0);
      return db.compareTo(da);
    });

    final form = <String>[];
    for (final match in finished.take(limit)) {
      final home = match['home'] as Map<String, dynamic>?;
      final isHome = home?['id'] == teamId;
      final homeScore = match['home_score'] as int? ?? 0;
      final awayScore = match['away_score'] as int? ?? 0;
      final scored = isHome ? homeScore : awayScore;
      final conceded = isHome ? awayScore : homeScore;

      if (scored > conceded) {
        form.add('G');
      } else if (scored < conceded) {
        form.add('P');
      } else {
        form.add('E');
      }
    }
    return form;
  }

  Future<int?> findPlayerId({required String name, String? teamName, int? tournamentId}) async {
    final normalizedName = name.trim().toLowerCase();
    Iterable<Map<String, dynamic>> teams;

    if (tournamentId != null) {
      teams = await fetchTeamsForTournament(tournamentId);
    } else {
      teams = [];
      for (final tournament in await fetchTournaments()) {
        teams = [...teams, ...await fetchTeamsForTournament(tournament['id'] as int)];
      }
    }

    for (final team in teams) {
      if (teamName != null && (team['name'] as String? ?? '').toLowerCase() != teamName.trim().toLowerCase()) {
        continue;
      }
      final detail = await fetchTeam(team['id'] as int);
      for (final player in (detail['players'] as List<dynamic>? ?? [])) {
        final map = Map<String, dynamic>.from(player as Map);
        if ((map['name'] as String? ?? '').trim().toLowerCase() == normalizedName) {
          return map['id'] as int?;
        }
      }
    }
    return null;
  }

  Future<List<Map<String, dynamic>>> fetchTeamsForTournament(int tournamentId) async {
    final tournament = await fetchTournament(tournamentId);
    final teams = <Map<String, dynamic>>[];

    for (final category in (tournament['categories'] as List<dynamic>? ?? [])) {
      final catId = (category as Map)['id'] as int;
      final detail = await fetchCategory(catId);
      for (final team in (detail['teams'] as List<dynamic>? ?? [])) {
        final map = Map<String, dynamic>.from(team as Map);
        map['category'] = (category)['name'];
        teams.add(map);
      }
    }
    return teams;
  }

  Future<List<Map<String, dynamic>>> fetchContent({int? tournamentId, String? type}) async {
    final query = <String, dynamic>{};
    if (tournamentId != null) query['tournament_id'] = tournamentId;
    if (type != null && type.isNotEmpty) query['type'] = type;

    final json = await _client.getJson('/content', query: query.isEmpty ? null : query);
    final response = ApiResponse<List<dynamic>>.fromJson(json, (data) => data as List);
    return (response.data ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> fetchContentBySlug(String slug) async {
    final json = await _client.getJson('/content/$slug');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<List<Map<String, dynamic>>> fetchNotifications() async {
    final json = await _client.getJson('/notifications');
    final response = ApiResponse<List<dynamic>>.fromJson(json, (data) => data as List);
    return (response.data ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> fetchFavorites() async {
    final json = await _client.getJson('/favorites');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<bool> toggleFavoriteTeam(int teamId) async {
    final json = await _client.postJson('/favorites/teams/$teamId');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data?['favorited'] as bool? ?? false;
  }

  Future<bool> toggleFavoriteMatch(int matchId) async {
    final json = await _client.postJson('/favorites/matches/$matchId');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data?['favorited'] as bool? ?? false;
  }

  Future<Map<String, dynamic>> fetchBrackets(int categoryId) async {
    final json = await _client.getJson('/categories/$categoryId/brackets');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchFairPlay(int categoryId) async {
    final json = await _client.getJson('/categories/$categoryId/fair-play');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<int, int>> fetchTeamCounts(List<Map<String, dynamic>> tournaments) async {
    final counts = <int, int>{};
    await Future.wait(tournaments.map((t) async {
      final id = t['id'] as int?;
      if (id == null) return;
      try {
        final detail = await fetchTournament(id);
        var total = 0;
        for (final category in (detail['categories'] as List<dynamic>? ?? [])) {
          final cat = await fetchCategory((category as Map)['id'] as int);
          total += (cat['teams'] as List<dynamic>? ?? []).length;
        }
        counts[id] = total;
      } catch (_) {}
    }));
    return counts;
  }
}
