<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $users = \App\Models\User::count();
        $problems = \App\Models\Problem::count();

        return [
            'user_id' => rand(1, $users),
            'problem_id' => rand(1, $problems),
            'language' => 'gcc-10',
            'code' => json_encode("#include <bits\/stdc++.h>\nusing namespace std;\n\nusing ll=long long;\nusing ld=long double;\nusing str=string;\n\nusing pi=pair<int,int>;\nusing pl=pair<ll,ll>;\nusing pd=pair<ld,ld>;\n#define mp make_pair\n#define f first\n#define s second\n\ntemplate<class T>using V=vector<T>;\nusing vi=V<int>;\nusing vb=V<bool>;\nusing vl=V<ll>;\nusing vd=V<ld>;\nusing vs=V<str>;\nusing vpi=V<pi>;\nusing vpl=V<pl>;\nusing vpd=V<pd>;\n\n#define sz(x) int((x).size())\n#define all(x) begin(x),end(x)\n#define rall(x) x.rbegin(), x.rend()\n#define sor(x) sort(all(x))\n#define rsz resize\n#define ins insert\n#define pb push_back\n#define eb emplace_back\n#define ft front()\n#define bk back()\n\n#define lb lower_bound\n#define ub upper_bound\ntemplate<class T> int lwb(V<T>&a,const T&b){return int(lb(all(a),b)-a.begin());}\ntemplate<class T> int upb(V<T>&a,const T&b){return int(ub(all(a),b)-a.begin());}\n\n#define FOR(i,a,b) for(int i=(a);i<=(b);++i)\n#define F0R(i,a) FOR(i,0,a-1)\n#define ROF(i,a,b) for(int i=(a);i>=(b);--i)\n#define R0F(i,a) ROF(i,a-1,0)\n#define rep(a) F0R(_,a)\n#define each(a,x) for(auto& a:x)\n\nconst int MOD=1e9+7;\/\/998244353;\nconst int MX=1e5+5;\nconst ll BIG=1e18;\nconst int dx[4]{1,0,-1,0}, dy[4]{0,1,0,-1};\nmt19937 rng((uint32_t)chrono::steady_clock::now().time_since_epoch().count());\ntemplate<class T> using pqg=priority_queue<T,vector<T>,greater<T>>;\n\nconstexpr int pct(int x){return __builtin_popcount(x);}\nconstexpr int bits(int x){return x==0?0:31-__builtin_clz(x);}\nconstexpr int p2(int x){return 1<<x;}\n\nll cdiv(ll a, ll b){return a\/b+((a^b)>0&&a%b);}\nll fdiv(ll a, ll b){return a\/b-((a^b)<0&&a%b);}\n\ntemplate<class T>bool ckmin(T&a,const T&b){return b<a?a=b,1:0;}\ntemplate<class T>bool ckmax(T&a,const T&b){return a<b?a=b,1:0;}\n\nint main()\n{\n\tios_base::sync_with_stdio(0);\n\tcin.tie(0);cout.tie(0);\n\t\n\tint n;\n\tcin>>n;\n\tcout<<n;\n}\n"),
            'verdict' => 'Accepted',
            'outputs' => '[{"input":"1","output":"1","expected_output":"1","log":"OK","time":20,"memory":3480},{"input":"2","output":"2","expected_output":"2","log":"OK","time":0,"memory":3532}]',
        ];
    }
}
